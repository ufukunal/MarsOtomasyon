<?php

namespace App\Modules\Treasury;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Treasury\Actions\BankStatementService;
use App\Modules\Treasury\Actions\TreasuryOperations;
use App\Modules\Treasury\Models\TreasuryAccount;
use App\Modules\Treasury\Models\TreasuryPayment;
use App\Modules\Treasury\Models\TreasuryPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final readonly class TreasuryController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private TreasuryOperations $operations,
        private BankStatementService $statements,
    ) {}

    public function index(): View
    {
        $companyId = $this->companyId();
        return view('treasury.index', [
            'treasuryAccounts' => DB::table('treasury_accounts as ta')->leftJoin('treasury_balances as tb', function ($join): void {
                $join->on('tb.company_id','=','ta.company_id')->on('tb.treasury_account_id','=','ta.id');
            })->where('ta.company_id',$companyId)->select('ta.*', DB::raw('COALESCE(tb.balance,0)::numeric(20,6) as balance'))->orderBy('ta.type')->orderBy('ta.code')->get(),
            'paymentMethods' => TreasuryPaymentMethod::query()->where('company_id',$companyId)->orderBy('name')->get(),
            'commercialAccounts' => Account::query()->where('company_id',$companyId)->where('status','active')->orderBy('legal_name')->get(['id','code','legal_name','type','book_currency_code']),
            'payments' => DB::table('treasury_payments as p')->join('accounts as a','a.id','=','p.account_id')->join('treasury_accounts as ta','ta.id','=','p.treasury_account_id')->where('p.company_id',$companyId)->select('p.*','a.legal_name as account_name','ta.name as treasury_account_name')->orderByDesc('p.payment_date')->orderByDesc('p.id')->limit(100)->get(),
            'posPayments' => DB::table('treasury_payments as p')->join('accounts as a','a.id','=','p.account_id')->where('p.company_id',$companyId)->whereIn('p.payment_kind',['pos','virtual_pos'])->select('p.*','a.legal_name as account_name')->orderByDesc('p.id')->limit(100)->get(),
            'transfers' => DB::table('treasury_transfers')->where('company_id',$companyId)->orderByDesc('id')->limit(50)->get(),
            'expenses' => DB::table('treasury_expenses')->where('company_id',$companyId)->orderByDesc('id')->limit(50)->get(),
            'cashCounts' => DB::table('treasury_cash_counts')->where('company_id',$companyId)->orderByDesc('id')->limit(50)->get(),
            'statementLines' => DB::table('bank_statement_lines')->where('company_id',$companyId)->orderByDesc('booking_date')->orderByDesc('id')->limit(100)->get(),
            'movements' => DB::table('treasury_movements as tm')->join('treasury_accounts as ta','ta.id','=','tm.treasury_account_id')->where('tm.company_id',$companyId)->select('tm.*','ta.name as treasury_account_name')->orderByDesc('tm.posting_date')->orderByDesc('tm.id')->limit(150)->get(),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'=>['required','in:cash,bank,pos'], 'code'=>['required','string','max:64','regex:/^[A-Za-z0-9]+(?:[._-][A-Za-z0-9]+)*$/'],
            'name'=>['required','string','max:160'], 'currency_code'=>['required','string','size:3'], 'bank_name'=>['nullable','string','max:160'],
            'iban'=>['nullable','string','max:34'], 'account_number'=>['nullable','string','max:80'], 'pos_provider'=>['nullable','string','max:120'],
        ]);
        $type=(string)$data['type'];
        TreasuryAccount::query()->create([
            'company_id'=>$this->companyId(),'type'=>$type,'code'=>trim((string)$data['code']),'name'=>trim((string)$data['name']),
            'currency_code'=>strtoupper((string)$data['currency_code']),'is_active'=>true,
            'bank_name'=>$type==='cash'?null:($data['bank_name']??null),'iban'=>$type==='bank'?($data['iban']??null):null,
            'account_number'=>$type==='cash'?null:($data['account_number']??null),'pos_provider'=>$type==='pos'?($data['pos_provider']??null):null,
        ]);
        return back()->with('status','Treasury hesabı oluşturuldu.');
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        $data=$request->validate(['code'=>['required','string','max:64'],'name'=>['required','string','max:160'],'kind'=>['required','in:cash,bank,pos,virtual_pos,cheque,promissory_note,other'],'treasury_account_id'=>['nullable','integer']]);
        $accountId=isset($data['treasury_account_id'])?(int)$data['treasury_account_id']:null;
        if ($accountId!==null) $this->treasuryAccount($accountId);
        TreasuryPaymentMethod::query()->create(['company_id'=>$this->companyId(),'code'=>trim((string)$data['code']),'name'=>trim((string)$data['name']),'kind'=>(string)$data['kind'],'treasury_account_id'=>$accountId,'is_active'=>true]);
        return back()->with('status','Ödeme yöntemi oluşturuldu.');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data=$request->validate(['direction'=>['required','in:collection,payment'],'account_id'=>['required','integer'],'treasury_account_id'=>['required','integer'],'payment_method_id'=>['required','integer'],'payment_date'=>['required','date_format:Y-m-d'],'amount'=>['required','decimal:0,6','gt:0'],'reference'=>['nullable','string','max:120'],'note'=>['nullable','string','max:5000']]);
        $companyId=$this->companyId();
        $account=Account::query()->where('company_id',$companyId)->whereKey((int)$data['account_id'])->where('status','active')->firstOrFail();
        $treasury=$this->treasuryAccount((int)$data['treasury_account_id']);
        $method=TreasuryPaymentMethod::query()->where('company_id',$companyId)->whereKey((int)$data['payment_method_id'])->where('is_active',true)->firstOrFail();
        if ((string)$account->book_currency_code!==(string)$treasury->currency_code) abort(422,'Cari ve treasury para birimi aynı olmalıdır.');
        if ($method->treasury_account_id!==null && (int)$method->treasury_account_id!==(int)$treasury->getKey()) abort(422,'Ödeme yöntemi farklı treasury hesabına bağlı.');
        $direction=(string)$data['direction'];
        $accountType=(string)$account->type;
        if ($direction==='collection' && !in_array($accountType,['customer','mixed'],true)) abort(422,'Tahsilat müşteri veya mixed cariden yapılabilir.');
        if ($direction==='payment' && !in_array($accountType,['supplier','mixed'],true)) abort(422,'Ödeme tedarikçi veya mixed cariye yapılabilir.');
        $kind=(string)$method->kind;
        if (in_array($kind,['pos','virtual_pos'],true) && ((string)$treasury->type!=='pos' || $direction!=='collection')) abort(422,'POS/Sanal POS yalnız POS hesabına müşteri tahsilatı olarak kaydedilir.');
        TreasuryPayment::query()->create([
            'company_id'=>$companyId,'account_id'=>$account->getKey(),'treasury_account_id'=>$treasury->getKey(),'payment_method_id'=>$method->getKey(),
            'direction'=>$direction,'payment_kind'=>$kind,'status'=>'draft','pos_status'=>in_array($kind,['pos','virtual_pos'],true)?'pending':null,
            'payment_date'=>(string)$data['payment_date'],'currency_code'=>$treasury->currency_code,'amount'=>(string)$data['amount'],
            'reference'=>$data['reference']??null,'note'=>$data['note']??null,
        ]);
        return back()->with('status','Tahsilat/ödeme taslağı oluşturuldu.');
    }

    public function finalizePayment(int $payment): RedirectResponse { $this->operations->finalizePayment($this->companyId(),$payment); return back()->with('status','Tahsilat/ödeme cari + treasury etkileriyle kesinleştirildi.'); }
    public function reversePayment(int $payment): RedirectResponse { $this->operations->reversePayment($this->companyId(),$payment); return back()->with('status','Tahsilat/ödeme ters kayıtla kapatıldı.'); }

    public function settlePos(Request $request,int $payment): RedirectResponse
    {
        $data=$request->validate(['bank_account_id'=>['required','integer'],'settlement_date'=>['required','date_format:Y-m-d'],'commission_amount'=>['required','decimal:0,6','min:0']]);
        $this->operations->settlePos($this->companyId(),$payment,(int)$data['bank_account_id'],(string)$data['settlement_date'],(string)$data['commission_amount']);
        return back()->with('status','POS settlement işlendi; brüt, komisyon ve net ayrıştırıldı.');
    }
    public function chargebackPos(Request $request,int $payment): RedirectResponse
    {
        $data=$request->validate(['chargeback_date'=>['required','date_format:Y-m-d']]);
        $this->operations->chargebackPos($this->companyId(),$payment,(string)$data['chargeback_date']);
        return back()->with('status','POS chargeback işlendi ve müşteri borcu yeniden açıldı.');
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $data=$request->validate(['from_account_id'=>['required','integer','different:to_account_id'],'to_account_id'=>['required','integer'],'transfer_date'=>['required','date_format:Y-m-d'],'amount'=>['required','decimal:0,6','gt:0'],'note'=>['nullable','string','max:5000']]);
        $from=$this->treasuryAccount((int)$data['from_account_id']); $to=$this->treasuryAccount((int)$data['to_account_id']);
        if ((string)$from->currency_code!==(string)$to->currency_code) abort(422,'M10 V1 virmanları aynı para biriminde olmalıdır.');
        DB::table('treasury_transfers')->insert(['company_id'=>$this->companyId(),'from_account_id'=>$from->getKey(),'to_account_id'=>$to->getKey(),'status'=>'draft','transfer_date'=>$data['transfer_date'],'currency_code'=>$from->currency_code,'amount'=>$data['amount'],'note'=>$data['note']??null,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status','Virman taslağı oluşturuldu.');
    }
    public function finalizeTransfer(int $transfer): RedirectResponse { $this->operations->finalizeTransfer($this->companyId(),$transfer); return back()->with('status','Virman iki taraflı kesinleştirildi.'); }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data=$request->validate(['treasury_account_id'=>['required','integer'],'expense_date'=>['required','date_format:Y-m-d'],'amount'=>['required','decimal:0,6','gt:0'],'category'=>['required','string','max:120'],'note'=>['nullable','string','max:5000']]);
        $account=$this->treasuryAccount((int)$data['treasury_account_id']);
        DB::table('treasury_expenses')->insert(['company_id'=>$this->companyId(),'treasury_account_id'=>$account->getKey(),'status'=>'draft','expense_date'=>$data['expense_date'],'currency_code'=>$account->currency_code,'amount'=>$data['amount'],'category'=>trim((string)$data['category']),'note'=>$data['note']??null,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status','Masraf taslağı oluşturuldu.');
    }
    public function finalizeExpense(int $expense): RedirectResponse { $this->operations->finalizeExpense($this->companyId(),$expense); return back()->with('status','Masraf treasury çıkışı olarak kesinleştirildi.'); }

    public function storeCashCount(Request $request): RedirectResponse
    {
        $data=$request->validate(['treasury_account_id'=>['required','integer'],'count_date'=>['required','date_format:Y-m-d'],'note'=>['nullable','string','max:5000'],'lines'=>['required','array','min:1','max:50'],'lines.*.denomination'=>['required','decimal:0,6','gt:0'],'lines.*.quantity'=>['required','integer','min:0','max:1000000']]);
        $account=$this->treasuryAccount((int)$data['treasury_account_id']); if ((string)$account->type!=='cash') abort(422,'Kasa sayımı cash hesabında yapılır.');
        DB::transaction(function () use($data,$account):void {
            $id=(int)DB::table('treasury_cash_counts')->insertGetId(['company_id'=>$this->companyId(),'treasury_account_id'=>$account->getKey(),'status'=>'draft','count_date'=>$data['count_date'],'currency_code'=>$account->currency_code,'note'=>$data['note']??null,'created_at'=>now(),'updated_at'=>now()]);
            foreach($data['lines'] as $line){
                $row=DB::selectOne('SELECT (?::numeric(20,6) * ?::integer)::numeric(20,6)::text AS total',[(string)$line['denomination'],(int)$line['quantity']]);
                DB::table('treasury_cash_count_lines')->insert(['company_id'=>$this->companyId(),'treasury_cash_count_id'=>$id,'denomination'=>$line['denomination'],'quantity'=>$line['quantity'],'line_total'=>(string)$row->total,'created_at'=>now(),'updated_at'=>now()]);
            }
        });
        return back()->with('status','Kasa sayımı taslağı oluşturuldu.');
    }
    public function finalizeCashCount(int $count): RedirectResponse { $this->operations->finalizeCashCount($this->companyId(),$count); return back()->with('status','Kasa sayımı kesinleştirildi; fark varsa adjustment işlendi.'); }

    public function importStatement(Request $request): RedirectResponse
    {
        $data=$request->validate(['treasury_account_id'=>['required','integer'],'format'=>['required','in:csv,xlsx,mt940'],'statement'=>['required','file','max:10240']]);
        $id=$this->statements->import($this->companyId(),(int)$data['treasury_account_id'],(string)$data['format'],$data['statement']);
        return back()->with('status','Banka ekstresi içe aktarıldı (#'.$id.').');
    }
    public function matchStatement(Request $request,int $line): RedirectResponse { $data=$request->validate(['movement_id'=>['required','integer']]); $this->statements->match($this->companyId(),$line,(int)$data['movement_id']); return back()->with('status','Ekstre satırı treasury hareketiyle eşleştirildi.'); }
    public function ignoreStatement(int $line): RedirectResponse { $this->statements->ignore($this->companyId(),$line); return back()->with('status','Ekstre satırı yok sayıldı.'); }

    private function treasuryAccount(int $id): TreasuryAccount
    {
        return TreasuryAccount::query()->where('company_id',$this->companyId())->whereKey($id)->where('is_active',true)->firstOrFail();
    }
    private function companyId(): int { return (int)$this->companyContext->requireCompany()->getKey(); }
}
