# Dernek Yazılımı – WordPress eklentisi

[Dernek Yazılımı](https://github.com/lkdtr/dernekyazilimi) portalının bağış, gönüllü ve üyelik formlarını WordPress sitesine koyar. Formları sitenin teması çizer; ödeme ve başvurunun devamı portalın çerçevesinde sürer. Kullanım ve dış servis açıklaması: [readme.txt](readme.txt).

## Geliştirme

- Derleme adımı yok: PHP 7.4+, düz JS/CSS.
- Çeviri: `languages/dernekyazilimi-tr_TR.po` değişince `msgfmt -o languages/dernekyazilimi-tr_TR.mo languages/dernekyazilimi-tr_TR.po`.
- Portal tarafı: `/api/site/*` (portal reposunda `App\Support\SiteApi`).
- Paket: `git archive --format=zip --prefix=dernekyazilimi/ -o dernekyazilimi.zip HEAD` (`.gitattributes` dağıtıma girmeyenleri ayıklar).
- WordPress.org'a göndermeden önce [Plugin Check](https://wordpress.org/plugins/plugin-check/) ile denetleyin.
# dernekyazilimi-wordpress
