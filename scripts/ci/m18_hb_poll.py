from pathlib import Path

service = Path('app/Modules/Commerce/ChannelCenterService.php')
text = service.read_text()

marker = "        if ($provider !== 'trendyol') {\n            throw new DomainException('Order polling is not implemented for provider.');\n        }\n"
block = r'''        if ($provider === 'hepsiburada') {
            return $this->pollHepsiburadaOrders($connection, $credentials, $modifiedAfter, $page, $perPage);
        }

        if ($provider !== 'trendyol') {
            throw new DomainException('Order polling is not implemented for provider.');
        }
'''
if marker not in text:
    raise SystemExit('Hepsiburada poll insertion marker not found')
text = text.replace(marker, block, 1)

marker = "    public function queueInvoiceSync(int $companyId, string $connectionPublicId, int $salesInvoiceId, string $externalOrderId): string\n"
methods = r'''    /**
     * @param  object{id:int,company_id:int,provider:mixed}  $connection
     * @param  array<string,mixed>  $credentials
     * @return list<int>
     */
    private function pollHepsiburadaOrders(object $connection, array $credentials, ?string $modifiedAfter, int $page, int $perPage): array
    {
        if ($modifiedAfter !== null && trim($modifiedAfter) !== '') {
            throw new DomainException('Hepsiburada date-window polling is disabled until the provider date format is contract-locked.');
        }

        $response = $this->hepsiburada->paidOrders($credentials, [
            'offset' => ($page - 1) * $perPage,
            'limit' => $perPage,
        ]);
        if ($response->status() === 429) {
            throw new DomainException('Hepsiburada polling is rate limited.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Hepsiburada polling returned HTTP '.$response->status().'.');
        }

        $body = $response->json();
        $items = is_array($body) ? ($body['items'] ?? null) : null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('Hepsiburada polling response must contain an items list.');
        }

        $orderNumbers = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('Hepsiburada polling returned an invalid order line.');
            }
            $orderNumber = isset($item['orderNumber']) && is_scalar($item['orderNumber']) ? trim((string) $item['orderNumber']) : '';
            if ($orderNumber === '') {
                throw new RuntimeException('Hepsiburada polling returned a line without orderNumber.');
            }
            $orderNumbers[$orderNumber] = true;
        }

        $eventIds = [];
        foreach (array_keys($orderNumbers) as $orderNumber) {
            $detail = $this->hepsiburada->orderDetail($credentials, $orderNumber);
            if ($detail->status() === 429) {
                throw new DomainException('Hepsiburada order-detail polling is rate limited.');
            }
            if (! $detail->successful()) {
                throw new RuntimeException('Hepsiburada order detail returned HTTP '.$detail->status().'.');
            }
            $detailBody = $detail->json();
            $detailItems = is_array($detailBody) && array_is_list($detailBody)
                ? $detailBody
                : (is_array($detailBody) && is_array($detailBody['items'] ?? null) ? $detailBody['items'] : null);
            if (! is_array($detailItems) || ! array_is_list($detailItems) || $detailItems === []) {
                throw new RuntimeException('Hepsiburada order detail must contain an order-line list.');
            }

            $payload = $this->normalizeHepsiburadaOrder($orderNumber, $detailItems);
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $status = isset($payload['status']) && is_scalar($payload['status']) ? strtolower((string) $payload['status']) : 'open';
            $externalEventId = 'hb-poll-'.$orderNumber.'-'.substr(hash('sha256', $encoded), 0, 32);
            $eventIds[] = $this->events->persist($connection, $externalEventId, 'order.'.$status, $payload);
        }

        return $eventIds;
    }

    /**
     * @param  list<mixed>  $items
     * @return array<string,mixed>
     */
    private function normalizeHepsiburadaOrder(string $orderNumber, array $items): array
    {
        $lines = [];
        $currency = '';
        $orderDate = '';
        $customerName = '';
        $status = 'open';

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('Hepsiburada order detail contains an invalid line.');
            }
            $itemOrderNumber = isset($item['orderNumber']) && is_scalar($item['orderNumber']) ? trim((string) $item['orderNumber']) : $orderNumber;
            if ($itemOrderNumber !== $orderNumber) {
                throw new RuntimeException('Hepsiburada order detail mixed multiple order numbers.');
            }
            $sku = '';
            foreach (['merchantSku', 'sku'] as $key) {
                if (isset($item[$key]) && is_scalar($item[$key]) && trim((string) $item[$key]) !== '') {
                    $sku = trim((string) $item[$key]);
                    break;
                }
            }
            if ($sku === '') {
                throw new RuntimeException('Hepsiburada order line requires merchantSku or sku.');
            }
            $quantity = $item['quantity'] ?? null;
            if (! is_numeric($quantity) || (float) $quantity <= 0) {
                throw new RuntimeException('Hepsiburada order line quantity is invalid.');
            }

            $unitPrice = null;
            foreach (['unitPrice', 'price'] as $priceKey) {
                $candidate = $item[$priceKey] ?? null;
                if (is_array($candidate) && isset($candidate['amount']) && is_numeric($candidate['amount'])) {
                    $unitPrice = $candidate['amount'];
                    if ($currency === '' && isset($candidate['currency']) && is_scalar($candidate['currency'])) {
                        $currency = strtoupper(trim((string) $candidate['currency']));
                    }
                    break;
                }
                if (is_numeric($candidate)) {
                    $unitPrice = $candidate;
                    break;
                }
            }
            if ($unitPrice === null && isset($item['merchantUnitPrice']) && is_numeric($item['merchantUnitPrice'])) {
                $unitPrice = $item['merchantUnitPrice'];
            }
            if ($unitPrice === null) {
                throw new RuntimeException('Hepsiburada order line requires a unit price.');
            }
            if ($currency === '' && is_array($item['totalPrice'] ?? null) && isset($item['totalPrice']['currency']) && is_scalar($item['totalPrice']['currency'])) {
                $currency = strtoupper(trim((string) $item['totalPrice']['currency']));
            }

            $name = '';
            foreach (['name', 'productName'] as $key) {
                if (isset($item[$key]) && is_scalar($item[$key]) && trim((string) $item[$key]) !== '') {
                    $name = trim((string) $item[$key]);
                    break;
                }
            }
            $lines[] = [
                'lineItemId' => isset($item['id']) && is_scalar($item['id']) ? (string) $item['id'] : null,
                'merchantSku' => $sku,
                'sku' => isset($item['sku']) && is_scalar($item['sku']) ? (string) $item['sku'] : null,
                'name' => $name,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
            ];

            if ($orderDate === '' && isset($item['orderDate']) && is_scalar($item['orderDate'])) {
                $orderDate = trim((string) $item['orderDate']);
            }
            if ($customerName === '' && isset($item['customerName']) && is_scalar($item['customerName'])) {
                $customerName = trim((string) $item['customerName']);
            }
            if (isset($item['status']) && is_scalar($item['status']) && trim((string) $item['status']) !== '') {
                $status = strtolower(trim((string) $item['status']));
            }
        }

        if ($currency === '') {
            $currency = 'TRY';
        }

        return [
            'orderNumber' => $orderNumber,
            'orderDate' => $orderDate,
            'currencyCode' => $currency,
            'status' => $status,
            'customerName' => $customerName,
            'billing' => $customerName === '' ? [] : ['company' => $customerName],
            'shipping' => $customerName === '' ? [] : ['company' => $customerName],
            'lines' => $lines,
            'provider_payload' => ['items' => $items],
        ];
    }

'''
if marker not in text:
    raise SystemExit('ChannelCenterService invoice marker not found')
text = text.replace(marker, methods + marker, 1)
service.write_text(text)

commerce = Path('config/commerce.php')
text = commerce.read_text()
old = "                'order_polling_contract',\n                'order_detail_contract',\n"
new = "                'order_polling',\n                'order_polling_contract',\n                'order_detail_contract',\n"
if old not in text:
    raise SystemExit('Hepsiburada order capability marker not found')
commerce.write_text(text.replace(old, new, 1))

m11 = Path('config/m11.php')
text = m11.read_text()
old = "        'supported_providers' => ['woocommerce', 'trendyol'],\n"
new = "        'supported_providers' => ['woocommerce', 'trendyol', 'hepsiburada'],\n"
if old not in text:
    raise SystemExit('M11 provider allowlist marker not found')
m11.write_text(text.replace(old, new, 1))
