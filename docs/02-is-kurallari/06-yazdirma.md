# Yazdırma soyutlaması

Uygulama yazıcıya doğrudan konuşmaz. Tek kapı `PrintManager`dır.

## Profil

`print_profiles` Master DB'dedir. Çözümleme şirket+kullanıcı+makine+tip → şirket+kullanıcı+tip → şirket+tip → varsayılan sırasındadır.

Etiket ölçüsü `paper_code + width_mm + height_mm` ile verilir. `printer_name` fiziksel cihaz eşlemesidir. DPI/gap/darkness gibi taşıyıcı özel ek değerler profil `settings` alanında bulunabilir.

Marka/model sabitlenmez. ZPL genel şablon yaklaşımıdır; ileride AgentDriver/ShellDriver aynı PrintManager sözleşmesini uygular.
