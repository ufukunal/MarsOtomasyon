<?php

namespace App\Livewire\Pages\Contacts;

use App\Actions\Contacts\SaveContact;
use App\Actions\Contacts\SaveContactAddress;
use App\Actions\Contacts\SaveContactBank;
use App\Actions\Contacts\SaveContactPerson;
use App\Models\Period\Contact;
use App\Models\Period\ContactAddress;
use App\Models\Period\ContactBank;
use App\Models\Period\ContactCategory;
use App\Models\Period\ContactPerson;
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

    public ?int $addressId = null;
    public string $addressType = 'invoice';
    public string $addressTitle = '';
    public string $addressLine = '';
    public string $addressCity = '';
    public string $addressDistrict = '';
    public bool $addressDefault = false;
    public int $addressVersion = 1;

    public ?int $personId = null;
    public string $personName = '';
    public string $personTitle = '';
    public string $personPhone = '';
    public string $personEmail = '';
    public bool $personDefault = false;
    public int $personVersion = 1;

    public ?int $bankId = null;
    public string $bankName = '';
    public string $iban = '';
    public bool $bankDefault = false;
    public int $bankVersion = 1;

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
        $this->nationalId = auth()->user()?->can('contacts.sensitive.view')
            ? (string) $contact->national_id
            : '';
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
        $canViewSensitive = auth()->user()?->can('contacts.sensitive.view') ?? false;

        $rules = [
            'title' => ['required', 'max:255'],
            'type' => ['required', 'in:legal,real'],
            'taxOffice' => ['nullable', 'max:255'],
            'taxNumber' => ['nullable', 'max:20'],
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
        ];

        if ($canViewSensitive) {
            $rules['nationalId'] = ['nullable', 'digits:11'];
        }

        $data = $this->validate($rules);

        if ($data['taxNumber'] ?? null) {
            $duplicate = Contact::query()
                ->when($this->contact, fn ($q) => $q->whereKeyNot($this->contact->id))
                ->where('tax_number', $data['taxNumber'])
                ->first();

            if ($duplicate) {
                session()->flash(
                    'warning',
                    "Vergi no {$duplicate->code} · {$duplicate->title} kartında da kullanılıyor. Kayıt engellenmedi.",
                );
            }
        }

        $payload = [
            'title' => $data['title'],
            'type' => $data['type'],
            'tax_office' => $data['taxOffice'],
            'tax_number' => $data['taxNumber'],
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
        ];

        if ($canViewSensitive) {
            $payload['national_id'] = $data['nationalId'];
        }

        $saved = $action->handle($payload, $this->contact, $this->version);

        $this->redirectRoute('contacts.edit', ['contact' => $saved->id], navigate: false);
    }

    public function saveAddress(SaveContactAddress $action): void
    {
        abort_unless($this->contact, 422);

        $data = $this->validate([
            'addressType' => ['required', 'in:invoice,shipping'],
            'addressTitle' => ['nullable', 'max:255'],
            'addressLine' => ['required'],
            'addressCity' => ['nullable', 'max:60'],
            'addressDistrict' => ['nullable', 'max:60'],
        ]);

        $row = $this->addressId
            ? ContactAddress::query()->where('contact_id', $this->contact->id)->findOrFail($this->addressId)
            : null;

        $action->handle($this->contact, [
            'type' => $data['addressType'],
            'title' => $data['addressTitle'],
            'address' => $data['addressLine'],
            'city' => $data['addressCity'],
            'district' => $data['addressDistrict'],
            'is_default' => $this->addressDefault,
        ], $row, $this->addressVersion);

        $this->resetAddressEditor();
    }

    public function editAddress(int $id): void
    {
        $row = ContactAddress::query()->where('contact_id', $this->contact?->id)->findOrFail($id);
        $this->addressId = $row->id;
        $this->addressType = $row->type;
        $this->addressTitle = (string) $row->title;
        $this->addressLine = $row->address;
        $this->addressCity = (string) $row->city;
        $this->addressDistrict = (string) $row->district;
        $this->addressDefault = (bool) $row->is_default;
        $this->addressVersion = (int) $row->version;
    }

    public function deleteAddress(int $id): void
    {
        ContactAddress::query()->where('contact_id', $this->contact?->id)->findOrFail($id)->delete();
        $this->resetAddressEditor();
    }

    public function savePerson(SaveContactPerson $action): void
    {
        abort_unless($this->contact, 422);

        $data = $this->validate([
            'personName' => ['required', 'max:255'],
            'personTitle' => ['nullable', 'max:255'],
            'personPhone' => ['nullable', 'max:30'],
            'personEmail' => ['nullable', 'email'],
        ]);

        $row = $this->personId
            ? ContactPerson::query()->where('contact_id', $this->contact->id)->findOrFail($this->personId)
            : null;

        $action->handle($this->contact, [
            'name' => $data['personName'],
            'title' => $data['personTitle'],
            'phone' => $data['personPhone'],
            'email' => $data['personEmail'],
            'is_default' => $this->personDefault,
        ], $row, $this->personVersion);

        $this->resetPersonEditor();
    }

    public function editPerson(int $id): void
    {
        $row = ContactPerson::query()->where('contact_id', $this->contact?->id)->findOrFail($id);
        $this->personId = $row->id;
        $this->personName = $row->name;
        $this->personTitle = (string) $row->title;
        $this->personPhone = (string) $row->phone;
        $this->personEmail = (string) $row->email;
        $this->personDefault = (bool) $row->is_default;
        $this->personVersion = (int) $row->version;
    }

    public function deletePerson(int $id): void
    {
        ContactPerson::query()->where('contact_id', $this->contact?->id)->findOrFail($id)->delete();
        $this->resetPersonEditor();
    }

    public function saveBank(SaveContactBank $action): void
    {
        abort_unless($this->contact, 422);

        $data = $this->validate([
            'bankName' => ['nullable', 'max:255'],
            'iban' => ['required', 'string', 'max:34'],
        ]);

        $row = $this->bankId
            ? ContactBank::query()->where('contact_id', $this->contact->id)->findOrFail($this->bankId)
            : null;

        $action->handle($this->contact, [
            'bank_name' => $data['bankName'],
            'iban' => $data['iban'],
            'is_default' => $this->bankDefault,
        ], $row, $this->bankVersion);

        $this->resetBankEditor();
    }

    public function editBank(int $id): void
    {
        $row = ContactBank::query()->where('contact_id', $this->contact?->id)->findOrFail($id);
        $this->bankId = $row->id;
        $this->bankName = (string) $row->bank_name;
        $this->iban = $row->iban;
        $this->bankDefault = (bool) $row->is_default;
        $this->bankVersion = (int) $row->version;
    }

    public function deleteBank(int $id): void
    {
        ContactBank::query()->where('contact_id', $this->contact?->id)->findOrFail($id)->delete();
        $this->resetBankEditor();
    }

    public function maskedNationalId(): string
    {
        return SensitiveFieldMasker::nationalId(
            $this->contact?->national_id,
            auth()->user()?->can('contacts.sensitive.view') ?? false,
        ) ?? '';
    }

    private function resetAddressEditor(): void
    {
        $this->addressId = null;
        $this->addressType = 'invoice';
        $this->addressTitle = '';
        $this->addressLine = '';
        $this->addressCity = '';
        $this->addressDistrict = '';
        $this->addressDefault = false;
        $this->addressVersion = 1;
    }

    private function resetPersonEditor(): void
    {
        $this->personId = null;
        $this->personName = '';
        $this->personTitle = '';
        $this->personPhone = '';
        $this->personEmail = '';
        $this->personDefault = false;
        $this->personVersion = 1;
    }

    private function resetBankEditor(): void
    {
        $this->bankId = null;
        $this->bankName = '';
        $this->iban = '';
        $this->bankDefault = false;
        $this->bankVersion = 1;
    }

    public function render(): View
    {
        return view('livewire.pages.contacts.contact-form', [
            'categories' => ContactCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'priceLists' => PriceList::query()->where('is_active', true)->orderBy('name')->get(),
            'addresses' => $this->contact?->addresses()->orderByDesc('is_default')->orderBy('id')->get() ?? collect(),
            'people' => $this->contact?->people()->orderByDesc('is_default')->orderBy('id')->get() ?? collect(),
            'banks' => $this->contact?->banks()->orderByDesc('is_default')->orderBy('id')->get() ?? collect(),
        ])->layout('layouts.app', [
            'pageTitle' => $this->contact ? "Cari · {$this->code}" : 'Yeni Cari',
        ]);
    }
}
