from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f'guard failed: {path}: {old[:120]!r}')
    p.write_text(text.replace(old, new, 1))


center = 'app/Modules/Commerce/ChannelCenterService.php'
replace_once(
    center,
    'use App\\Modules\\Commerce\\Providers\\Hepsiburada\\HepsiburadaClient;\n',
    'use App\\Modules\\Commerce\\Providers\\Amazon\\AmazonSpApiClient;\nuse App\\Modules\\Commerce\\Providers\\Hepsiburada\\HepsiburadaClient;\n',
)
replace_once(
    center,
    '        private ProviderRegistry $providers,\n        private TrendyolClient $trendyol,\n        private HepsiburadaClient $hepsiburada,\n',
    '        private ProviderRegistry $providers,\n        private TrendyolClient $trendyol,\n        private HepsiburadaClient $hepsiburada,\n        private AmazonSpApiClient $amazon,\n',
)
replace_once(
    center,
    "            } elseif ($provider === 'hepsiburada') {\n                $response = $this->hepsiburada->connectionTest($credentials);\n                $label = 'Hepsiburada';\n            } else {",
    "            } elseif ($provider === 'hepsiburada') {\n                $response = $this->hepsiburada->connectionTest($credentials);\n                $label = 'Hepsiburada';\n            } elseif ($provider === 'amazon') {\n                $response = $this->amazon->connectionTest($credentials);\n                $label = 'Amazon SP-API';\n            } else {",
)
replace_once(
    center,
    "        $provider = (string) $connection->provider;\n        $externalProductId = trim((string) ($mapping->external_product_id ?? ''));\n",
    "        $provider = (string) $connection->provider;\n        $externalProductId = trim((string) ($mapping->external_product_id ?? ''));\n        $externalSku = trim((string) ($mapping->external_sku ?? ''));\n",
)
replace_once(
    center,
    "        $barcode = null;\n        if ($provider === 'woocommerce') {",
    "        $barcode = null;\n        $amazonProductType = null;\n        if ($provider === 'woocommerce') {",
)
replace_once(
    center,
    "            if ($barcode === '') {\n                throw new DomainException('Trendyol stock/price publishing requires mapping metadata.barcode.');\n            }\n        } else {\n            throw new DomainException('Desired-state publishing is not implemented for provider.');\n        }\n\n        return DB::transaction(function () use ($companyId, $connection, $mapping, $provider, $externalProductId, $barcode, $stock, $price, $currencyCode, $mediaUrls): array {",
    "            if ($barcode === '') {\n                throw new DomainException('Trendyol stock/price publishing requires mapping metadata.barcode.');\n            }\n        } elseif ($provider === 'amazon') {\n            if ($mediaUrls !== []) {\n                throw new DomainException('Amazon media publishing is schema-driven and is not available through generic desired-state publishing.');\n            }\n            if ($stock !== null && ! $this->providers->supports($provider, 'stock_publish')) {\n                throw new DomainException('Provider does not support stock publishing.');\n            }\n            if ($price !== null && ! $this->providers->supports($provider, 'price_publish')) {\n                throw new DomainException('Provider does not support price publishing.');\n            }\n            if ($externalSku === '') {\n                throw new DomainException('Amazon publishing requires mapping external_sku.');\n            }\n            $metadata = json_decode((string) ($mapping->metadata ?? ''), true);\n            $fulfillment = is_array($metadata) && isset($metadata['fulfillment']) && is_scalar($metadata['fulfillment'])\n                ? strtoupper(trim((string) $metadata['fulfillment']))\n                : 'FBM';\n            if (! in_array($fulfillment, ['FBM', 'FBA'], true)) {\n                throw new DomainException('Amazon mapping metadata.fulfillment must be FBM or FBA.');\n            }\n            if ($fulfillment === 'FBA' && $stock !== null) {\n                throw new DomainException('Amazon FBA stock is Amazon-authoritative and cannot be overwritten from Mars.');\n            }\n            if ($price !== null && $currencyCode === null) {\n                throw new DomainException('Amazon price publishing requires currency code.');\n            }\n            $amazonProductType = is_array($metadata) && isset($metadata['product_type']) && is_scalar($metadata['product_type'])\n                ? strtoupper(trim((string) $metadata['product_type']))\n                : 'PRODUCT';\n            if ($amazonProductType === '') {\n                $amazonProductType = 'PRODUCT';\n            }\n        } else {\n            throw new DomainException('Desired-state publishing is not implemented for provider.');\n        }\n\n        return DB::transaction(function () use ($companyId, $connection, $mapping, $provider, $externalProductId, $externalSku, $barcode, $amazonProductType, $stock, $price, $currencyCode, $mediaUrls): array {",
)
replace_once(
    center,
    "            if ($provider === 'woocommerce') {\n                $payload = [];",
    "            if ($provider === 'woocommerce') {\n                $payload = [];",
)
replace_once(
    center,
    "                $operation = 'product';\n                $entityId = $externalProductId;\n            } else {\n                $item = ['barcode' => $barcode];",
    "                $operation = 'product';\n                $entityId = $externalProductId;\n            } elseif ($provider === 'trendyol') {\n                $item = ['barcode' => $barcode];",
)
replace_once(
    center,
    "                $payload = ['items' => [$item]];\n                $operation = $stock !== null ? 'stock' : 'price';\n                $entityId = (string) $barcode;\n            }\n\n            $effectId = $this->channels->scheduleSync(",
    "                $payload = ['items' => [$item]];\n                $operation = $stock !== null ? 'stock' : 'price';\n                $entityId = (string) $barcode;\n            } else {\n                $payload = [\n                    'quantity' => $stock === null ? null : (int) $stock,\n                    'price' => $price,\n                    'currency_code' => $currencyCode,\n                    'product_type' => $amazonProductType ?? 'PRODUCT',\n                ];\n                $operation = $stock !== null ? 'stock' : 'price';\n                $entityId = $externalSku;\n            }\n\n            $effectId = $this->channels->scheduleSync(",
)

channel = 'app/Modules/Operations/ChannelService.php'
replace_once(
    channel,
    'use App\\Modules\\Commerce\\Providers\\Trendyol\\TrendyolClient;\n',
    'use App\\Modules\\Commerce\\Providers\\Amazon\\AmazonSpApiClient;\nuse App\\Modules\\Commerce\\Providers\\Trendyol\\TrendyolClient;\n',
)
replace_once(
    channel,
    '        private readonly ChannelEventStore $events,\n        private readonly TrendyolClient $trendyol,\n',
    '        private readonly ChannelEventStore $events,\n        private readonly TrendyolClient $trendyol,\n        private readonly AmazonSpApiClient $amazon,\n',
)
replace_once(
    channel,
    "        if ($provider === 'trendyol') {\n            return match ($operation) {\n                'stock', 'price' => $this->trendyol->updatePriceAndInventory($credentials, $payload),\n                default => throw new RuntimeException('Unsupported Trendyol sync operation.'),\n            };\n        }\n\n        throw new RuntimeException('Unsupported integration provider.');",
    "        if ($provider === 'trendyol') {\n            return match ($operation) {\n                'stock', 'price' => $this->trendyol->updatePriceAndInventory($credentials, $payload),\n                default => throw new RuntimeException('Unsupported Trendyol sync operation.'),\n            };\n        }\n\n        if ($provider === 'amazon') {\n            if (! in_array($operation, ['stock', 'price'], true)) {\n                throw new RuntimeException('Unsupported Amazon sync operation.');\n            }\n\n            return $this->amazon->patchDesiredState(\n                $credentials,\n                $entityId,\n                isset($payload['quantity']) && $payload['quantity'] !== null ? (int) $payload['quantity'] : null,\n                isset($payload['price']) && $payload['price'] !== null ? (string) $payload['price'] : null,\n                isset($payload['currency_code']) && $payload['currency_code'] !== null ? (string) $payload['currency_code'] : null,\n                isset($payload['product_type']) ? (string) $payload['product_type'] : 'PRODUCT',\n            );\n        }\n\n        throw new RuntimeException('Unsupported integration provider.');",
)
