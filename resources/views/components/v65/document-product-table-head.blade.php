@props(['priceLabel' => 'Fiyat'])
<thead>
    <tr>
        <th scope="col">#</th>
        <th scope="col">Ürün Kodu / Ürün Adı</th>
        <th scope="col" class="align-end">Miktar</th>
        <th scope="col">Birim</th>
        <th scope="col" class="align-end">{{ $priceLabel }}</th>
        <th scope="col" class="align-end">İsk. %</th>
        <th scope="col" class="align-end">KDV %</th>
        <th scope="col" class="align-end">Net Tutar</th>
        <th scope="col" class="align-end">KDV Dahil</th>
        <th scope="col">İşlem</th>
    </tr>
</thead>
