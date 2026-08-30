from pathlib import Path

service = Path('app/Modules/Commerce/ChannelCenterService.php')
text = service.read_text()

old = "use App\\Modules\\Commerce\\Providers\\Trendyol\\TrendyolClient;\n"
new = "use App\\Modules\\Commerce\\Providers\\Hepsiburada\\HepsiburadaClient;\nuse App\\Modules\\Commerce\\Providers\\Trendyol\\TrendyolClient;\n"
if old not in text:
    raise SystemExit('ChannelCenterService import marker not found')
text = text.replace(old, new, 1)

old = "        private ProviderRegistry $providers,\n        private TrendyolClient $trendyol,\n"
new = "        private ProviderRegistry $providers,\n        private TrendyolClient $trendyol,\n        private HepsiburadaClient $hepsiburada,\n"
if old not in text:
    raise SystemExit('ChannelCenterService constructor marker not found')
text = text.replace(old, new, 1)

old = "            } elseif ($provider === 'trendyol') {\n                $response = $this->trendyol->connectionTest($credentials);\n                $label = 'Trendyol';\n            } else {\n"
new = "            } elseif ($provider === 'trendyol') {\n                $response = $this->trendyol->connectionTest($credentials);\n                $label = 'Trendyol';\n            } elseif ($provider === 'hepsiburada') {\n                $response = $this->hepsiburada->connectionTest($credentials);\n                $label = 'Hepsiburada';\n            } else {\n"
if old not in text:
    raise SystemExit('ChannelCenterService provider marker not found')
text = text.replace(old, new, 1)
service.write_text(text)

config = Path('config/commerce.php')
text = config.read_text()
old = "                'connection_test_contract',\n                'listing_read_contract',\n"
new = "                'connection_test',\n                'connection_test_contract',\n                'listing_read_contract',\n"
if old not in text:
    raise SystemExit('Hepsiburada config capability marker not found')
config.write_text(text.replace(old, new, 1))

test = Path('tests/Unit/HepsiburadaContractTest.php')
text = test.read_text()
old = "        self::assertFalse($registry->supports('hepsiburada', 'connection_test'));\n"
new = "        self::assertTrue($registry->supports('hepsiburada', 'connection_test'));\n"
if old not in text:
    raise SystemExit('Hepsiburada connection-test assertion marker not found')
test.write_text(text.replace(old, new, 1))
