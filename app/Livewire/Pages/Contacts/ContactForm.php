<?php

namespace App\Livewire\Pages\Contacts;

use App\Actions\Contacts\SaveContact;
use App\Models\Period\Contact;
use App\Models\Period\ContactCategory;
use App\Models\Period\PriceList;
use App\Support\Security\SensitiveFieldMasker;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ContactForm extends Component
{
    public ?Contact $contact = null;
    public string $code = '';
    public string $title = '';
    public string $type = 'legal';
    public string $taxOffice = '';
    public string $taxNumber = '';
    public string $nationalId = '';
    public string $address = '';
    public string $city = '';
    public string $district = '';
    public string $phone = '';
    public string $email = '';
    public ?int $termDays = null;
    public string $riskLimit = '0.0000';
    public string $discountRate = '0.0000';
    public ?int $priceListId = null;
    public array $categoryIds = [];
    public bool $isActive = true;
    public int $version = 1;
    public string $activeTab = 'general';

    public function mount(?Contact $contact = null): void
    {
        $this->contact = $contact;
        $this->authorize($contact ? 'update' : 'create', $contact ?? Contact::class);

        if (! $contact) {
            return;
        }

        $this->code = $contact->code;
        $this->title = $contact->title;
        $this->type = $contact->type->value;
        $this->taxOffice = (string) $contact->tax_office;
        $this->taxNumber = (string) $contact->tax_number;
        $this->nationalId = (string) $contact->national_id;
        $this->address = (string) $contact->address;
        $this->city = (string) $contact->city;
        $this->district = (string) $contact->district;
        $this->phone = (string) $contact->phone;
        $this->email = (string) $contact->email;
        $this->termDays = $contact->term_days;
        $this->riskLimit = (string) $contact->risk_limit;
        $this->discountRate = (string) $contact->discount_rate;
        $this->priceListId = $contact->price_list_id;
        $this->categoryIds = $contact->categories()->pluck('contact_categories.id')->all();
        $this->isActive = (bool) $contact->is_active;
        $this->version = (int) $contact->version;
    }

    public function save(SaveContact $action): void
    {
        $data = $this->validate([
            'title' => ['required', 'max:255'],
            'type' => ['required', 'in:legal,real'],
            'taxOffice' => ['nullable', 'max:255'],
            'taxNumber' => ['nullable', 'max:20'],
            'nationalId' => ['nullable', 'digits:11'],
            'address' => ['nullable'],
            'city' => ['nullable', 'max:60'],
            'district' => ['nullable', 'max:60'],
            'phone' => ['nullable', 'max:30'],
            'email' => ['nullable', 'email'],
            'termDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'riskLimit' => ['required', 'decimal:0,4', 'min:0'],
            'discountRate' => ['required', 'decimal:0,4', 'between:0,100'],
            'priceListId' => ['nullable', 'integer'],
            'categoryIds' => ['array'],
            'isActive' => ['boolean'],
        ]);

        $saved = $action->handle([
            'title' => $data['title'],
            'type' => $data['type'],
            'tax_office' => $data['taxOffice'],
            'tax_number' => $data['taxNumber'],
            'national_id' => $data['nationalId'],
            'address' => $data['address'],
            'city' => $data['city'],
            'district' => $data['district'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'term_days' => $data['termDays'],
            'risk_limit' => $data['riskLimit'],
            'discount_rate' => $data['discountRate'],
            'price_list_id' => $data['priceListId'],
            'category_ids' => $data['categoryIds'],
            'is_active' => $data['isActive'],
        ], $this->contact, $this->version);

        $this->redirectRoute('contacts.edit', ['contact' => $saved->id], navigate: false);
    }

    public function maskedNationalId(): string
    {
        return SensitiveFieldMasker::nationalId(
            $this->nationalId,
            auth()->user()?->can('contacts.sensitive.view') ?? false,
        ) ?? '';
    }

    public function render(): View
    {
        return view('livewire.pages.contacts.contact-form', [
            'categories' => ContactCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'priceLists' => PriceList::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', [
            'pageTitle' => $this->contact ? "Cari · {$this->code}" : 'Yeni Cari',
        ]);
    }
}
