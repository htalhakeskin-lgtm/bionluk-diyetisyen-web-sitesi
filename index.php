<?php
/*
 * ==============================================================
 *  DİYETİSYEN WEB SİTESİ - index.php (Ziyaretçilerin gördüğü sayfa)
 * --------------------------------------------------------------
 *  - Tüm içerikler veriler.txt dosyasından okunur.
 *  - İçerikleri değiştirmek için: siteadresiniz.com/admin.php
 *  - Gereksinim: PHP 7.0 veya üzeri. Ek kurulum gerekmez.
 * ==============================================================
 */

$VERI_DOSYASI = __DIR__ . '/veriler.txt';

/* ---------------- Yardımcı fonksiyonlar ---------------- */

// veriler.txt dosyasını fopen / fread / fclose ile okur
function veri_oku($dosya)
{
    if (!is_file($dosya)) {
        return array();
    }
    $fp = @fopen($dosya, 'r');
    if (!$fp) {
        return array();
    }
    flock($fp, LOCK_SH);
    $icerik = '';
    while (!feof($fp)) {
        $icerik .= fread($fp, 8192);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    // Not Defteri gibi editörlerin dosya başına eklediği BOM karakterini temizle
    if (substr($icerik, 0, 3) === "\xEF\xBB\xBF") {
        $icerik = substr($icerik, 3);
    }
    $veri = json_decode($icerik, true);
    return is_array($veri) ? $veri : array();
}

function e($metin)
{
    return htmlspecialchars((string) $metin, ENT_QUOTES, 'UTF-8');
}

// al($V, 'genel.site_adi') => $V['genel']['site_adi'] (yoksa varsayılan)
function al($dizi, $yol, $varsayilan = '')
{
    foreach (explode('.', $yol) as $anahtar) {
        if (!is_array($dizi) || !array_key_exists($anahtar, $dizi)) {
            return $varsayilan;
        }
        $dizi = $dizi[$anahtar];
    }
    return is_string($varsayilan) && !is_scalar($dizi) ? $varsayilan : $dizi;
}

// Listeyi (slaytlar, hizmetler...) eksik alanları boş metinle tamamlayarak döndürür
function liste($dizi, $yol, $alanlar)
{
    $liste = al($dizi, $yol, array());
    $sonuc = array();
    if (!is_array($liste)) {
        return $sonuc;
    }
    foreach ($liste as $oge) {
        if (!is_array($oge)) {
            continue;
        }
        $temiz = array();
        foreach ($alanlar as $alan) {
            $temiz[$alan] = isset($oge[$alan]) && is_scalar($oge[$alan]) ? trim((string) $oge[$alan]) : '';
        }
        $sonuc[] = $temiz;
    }
    return $sonuc;
}

// javascript: gibi tehlikeli adresleri engeller, "www." ile başlayanlara https ekler
function link_temizle($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('~^www\.~i', $url)) {
        return 'https://' . $url;
    }
    if (preg_match('~^(https?:|mailto:|tel:)~i', $url)) {
        return $url;
    }
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url)) {
        return '';
    }
    return $url; // #bolum veya resim.jpg gibi göreli adresler
}

function ikon($sinif, $varsayilan = 'fa-solid fa-leaf')
{
    $sinif = trim(preg_replace('/[^a-z0-9 \-]/i', '', (string) $sinif));
    if ($sinif === '') {
        return $varsayilan;
    }
    if (!preg_match('/\bfa-(solid|regular|brands)\b/', $sinif)) {
        $sinif = 'fa-solid ' . $sinif;
    }
    return $sinif;
}

function youtube_id($adres)
{
    $adres = trim((string) $adres);
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $adres, $m)) {
        return $m[1];
    }
    return preg_match('~^[A-Za-z0-9_-]{11}$~', $adres) ? $adres : '';
}

// Boş satır = yeni paragraf, **metin** = kalın yazı
function metin_bicimle($metin)
{
    $metin = trim(str_replace(array("\r\n", "\r"), "\n", (string) $metin));
    if ($metin === '') {
        return '';
    }
    $html = '';
    foreach (preg_split("/\n[ \t]*\n/", $metin) as $paragraf) {
        $paragraf = e(trim($paragraf));
        $paragraf = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $paragraf);
        $html .= '<p>' . nl2br($paragraf, false) . '</p>';
    }
    return $html;
}

// *kelime* yazılan yeri yeşil italik yapar. $sonKelime true ise yıldız yoksa son kelimeyi vurgular.
function baslik_vurgula($baslik, $sonKelime = false)
{
    $baslik = e(trim($baslik));
    if (strpos($baslik, '*') !== false) {
        return preg_replace('/\*(.+?)\*/', '<em>$1</em>', $baslik);
    }
    if ($sonKelime) {
        $sonuc = preg_replace('/(\S+)$/u', '<em>$1</em>', $baslik, 1);
        return $sonuc === null ? $baslik : $sonuc;
    }
    return $baslik;
}

function yildizsiz($metin)
{
    return str_replace('*', '', (string) $metin);
}

function renk_gecerli($renk, $varsayilan)
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $renk) ? strtolower($renk) : $varsayilan;
}

// İki rengi karıştırır ($oran: ilk rengin ağırlığı, 0-1 arası)
function renk_karistir($renk1, $renk2, $oran)
{
    $a = sscanf(substr($renk1, 1), '%02x%02x%02x');
    $b = sscanf(substr($renk2, 1), '%02x%02x%02x');
    $c = array();
    for ($i = 0; $i < 3; $i++) {
        $c[] = (int) round($a[$i] * $oran + $b[$i] * (1 - $oran));
    }
    return vsprintf('#%02x%02x%02x', $c);
}

function renk_rgb($renk)
{
    return implode(', ', sscanf(substr($renk, 1), '%02x%02x%02x'));
}

// 0545 123 45 67 / 545... / +90 545... biçimlerini 905451234567 yapar
function wa_numara($numara)
{
    $numara = preg_replace('/\D+/', '', (string) $numara);
    if (substr($numara, 0, 2) === '00') { // 0090... uluslararası ön ek
        $numara = substr($numara, 2);
    }
    if (strlen($numara) === 11 && $numara[0] === '0') {
        $numara = '9' . $numara;
    } elseif (strlen($numara) === 10 && $numara[0] === '5') {
        $numara = '90' . $numara;
    }
    return $numara;
}

function wa_link($numara, $mesaj = '')
{
    if ($numara === '') {
        return '';
    }
    return 'https://wa.me/' . $numara . ($mesaj !== '' ? '?text=' . rawurlencode($mesaj) : '');
}

function tel_link($telefon)
{
    return 'tel:' . preg_replace('/[^\d+]/', '', (string) $telefon);
}

function bas_harf($isim)
{
    return preg_match('/\S/u', (string) $isim, $m) ? e($m[0]) : '?';
}

// Yasal metinlerde {site_adi}, {adres}, {telefon}, {eposta} yazan yerlere sitedeki güncel bilgiler gelir
function yasal_metin($metin)
{
    global $siteAdi, $adres, $telefon, $eposta;
    return metin_bicimle(str_replace(array('{site_adi}', '{adres}', '{telefon}', '{eposta}'), array($siteAdi, $adres, $telefon, $eposta), $metin));
}

// Form onay kutusu yazısında [metin] yazan yere aydınlatma metni bağlantısı gelir
function form_onay_html($metin)
{
    $link = '<a href="#kvkk-aydinlatma-metni" data-metin="kvkk">KVKK Aydınlatma Metni</a>';
    $metin = e($metin);
    return strpos($metin, '[metin]') !== false ? str_replace('[metin]', $link, $metin) : $metin . ' (' . $link . ')';
}

/* ---------------- Fotoğraf hızlandırma ----------------
 * Büyük PNG/JPG fotoğraflar wsrv.nl (ücretsiz görsel servisi) üzerinden
 * ekran boyutuna göre küçültülüp WebP olarak gösterilir. Orijinal dosyaya
 * dokunulmaz; servis cevap vermezse tarayıcı otomatik olarak orijinali yükler.
 */

// "resim.jpg" gibi göreli adresleri tam adrese çevirir. Yerel bilgisayardaki adresler için '' döner.
function mutlak_adres($adres)
{
    $adres = trim((string) $adres);
    if ($adres === '' || $adres[0] === '#') {
        return '';
    }
    if (strpos($adres, '//') === 0) {
        $adres = 'https:' . $adres;
    }
    if (!preg_match('~^https?://~i', $adres)) {
        if (empty($_SERVER['HTTP_HOST'])) {
            return '';
        }
        $sema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $klasor = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $adres = $sema . '://' . $_SERVER['HTTP_HOST'] . ($adres[0] === '/' ? '' : $klasor . '/') . $adres;
    }
    $sunucu = strtolower((string) parse_url($adres, PHP_URL_HOST));
    if ($sunucu === '' || $sunucu === 'localhost' || preg_match('~^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|\[)~', $sunucu)
        || preg_match('~\.(local|test|localhost)$~', $sunucu)) {
        return '';
    }
    return $adres;
}

function hizli_resim($adres, $genislik)
{
    global $GORSEL_HIZLANDIR;
    $tam = $GORSEL_HIZLANDIR ? mutlak_adres($adres) : '';
    if ($tam === '' || preg_match('~\.(svg|gif)(\?|#|$)~i', $tam)) {
        return $adres;
    }
    return 'https://wsrv.nl/?url=' . rawurlencode($tam) . '&w=' . (int) $genislik . '&output=webp&q=78&we';
}

// <img> için src, srcset, sizes ve yedek (orijinal) adres özniteliklerini üretir
function resim_oznitelik($adres, $genislikler, $boyutlar)
{
    $adres = link_temizle($adres);
    if (hizli_resim($adres, $genislikler[0]) === $adres) {
        return 'src="' . e($adres) . '"';
    }
    $set = array();
    foreach ($genislikler as $g) {
        $set[] = hizli_resim($adres, $g) . ' ' . $g . 'w';
    }
    $varsayilan = hizli_resim($adres, $genislikler[min(1, count($genislikler) - 1)]);
    return 'src="' . e($varsayilan) . '" srcset="' . e(implode(', ', $set)) . '" sizes="' . e($boyutlar)
        . '" data-asil="' . e($adres) . '" onerror="resimYedek(this)"';
}

/* ---------------- Verileri yükle ---------------- */

$V = veri_oku($VERI_DOSYASI);
$veriYok = empty($V);

$siteAdi      = al($V, 'genel.site_adi', 'Diyetisyen');
$sayfaBasligi = al($V, 'genel.sayfa_basligi', $siteAdi);
$siteAciklama = al($V, 'genel.site_aciklamasi');
$logoIkon     = ikon(al($V, 'genel.logo_ikon'));
$waNumara     = wa_numara(al($V, 'genel.whatsapp'));
$waMesaj      = al($V, 'genel.whatsapp_mesaj', 'Merhaba, bilgi almak istiyorum.');
$waLink       = wa_link($waNumara, $waMesaj);
$GORSEL_HIZLANDIR = (bool) al($V, 'genel.gorsel_hizlandir', true);
$HERO_BOYUT = '(max-width: 900px) 76vw, 460px';

$ana        = renk_gecerli(al($V, 'genel.ana_renk'), '#1a8050');
$anaKoyu    = renk_karistir($ana, '#000000', 0.8);
$anaAcik    = renk_karistir($ana, '#ffffff', 0.12);
$anaAcik2   = renk_karistir($ana, '#ffffff', 0.32);
$anaZemin   = renk_karistir($ana, '#fbfaf6', 0.05);
$koyuZemin  = renk_karistir($ana, '#08140e', 0.26);

$slaytlar   = liste($V, 'hero.slaytlar', array('ust_baslik', 'baslik', 'aciklama', 'gorsel', 'buton_yazi', 'buton_link'));
$heroKartlar = array();
for ($i = 1; $i <= 2; $i++) {
    $sayi = trim(al($V, 'hero.kart' . $i . '_sayi'));
    $yazi = trim(al($V, 'hero.kart' . $i . '_yazi'));
    if ($sayi !== '' || $yazi !== '') {
        $heroKartlar[$i] = array('sayi' => $sayi, 'yazi' => $yazi);
    }
}

$hizmetler  = liste($V, 'hizmetler.liste', array('ikon', 'baslik', 'aciklama'));
$istatistik = liste($V, 'istatistik.liste', array('on_ek', 'sayi', 'son_ek', 'etiket'));
$istGoster  = (bool) al($V, 'istatistik.goster', true) && count($istatistik) > 0;
$vkiGoster  = (bool) al($V, 'vki.goster', true);
$vkiUyari   = al($V, 'vki.uyari', 'VKİ, 18 yaş üstü yetişkinler için genel bir göstergedir. Kas oranı, yaş, hamilelik ve sağlık durumunuz kesin değerlendirme için ayrıca ele alınmalıdır.');

$galeri = array();
foreach (liste($V, 'galeri.liste', array('tur', 'adres', 'baslik')) as $g) {
    if ($g['tur'] === 'video') {
        $id = youtube_id($g['adres']);
        if ($id === '') {
            continue;
        }
        $galeri[] = array('tur' => 'video', 'kaynak' => $id, 'buyuk' => '', 'kucuk' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
            'link' => 'https://www.youtube.com/watch?v=' . $id, 'baslik' => $g['baslik']);
    } else {
        $adres = link_temizle($g['adres']);
        if ($adres === '') {
            continue;
        }
        $galeri[] = array('tur' => 'resim', 'kaynak' => $adres, 'buyuk' => hizli_resim($adres, 1600), 'link' => $adres, 'baslik' => $g['baslik']);
    }
}
$galeriResim = 0;
foreach ($galeri as $g) {
    if ($g['tur'] === 'resim') {
        $galeriResim++;
    }
}
$galeriVideo = count($galeri) - $galeriResim;

$blog     = liste($V, 'blog.liste', array('ikon', 'gorsel', 'tarih', 'baslik', 'ozet', 'icerik'));
$yorumlar = liste($V, 'yorumlar.liste', array('isim', 'yorum'));
$sss      = liste($V, 'sss.liste', array('soru', 'cevap'));

$hkMetin   = al($V, 'hakkinda.metin');
$hkListe   = array_filter(array_map('trim', explode("\n", str_replace("\r", '', al($V, 'hakkinda.one_cikanlar')))), 'strlen');

$adres     = al($V, 'iletisim.adres');
$telefon   = al($V, 'iletisim.telefon');
$eposta    = al($V, 'iletisim.eposta');
$saatler   = array_filter(array_map('trim', explode("\n", str_replace("\r", '', al($V, 'iletisim.saatler')))), 'strlen');
$haritaLink = link_temizle(al($V, 'iletisim.harita_link'));
if ($haritaLink === '' && $adres !== '') {
    $haritaLink = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($adres);
}
$haritaGoster = (bool) al($V, 'iletisim.harita_goster', true) && $adres !== '';
$aramaButonu  = (bool) al($V, 'iletisim.arama_butonu', true) && preg_match('/\d/', $telefon);

// KVKK ve çerezler
$kvkkMetni  = al($V, 'kvkk.aydinlatma_metni');
$cerezMetni = al($V, 'kvkk.cerez_metni');
$cerezBandi = (bool) al($V, 'kvkk.bant_goster', true);
$bantBaslik = al($V, 'kvkk.bant_baslik', 'Çerez Tercihleriniz');
$bantMetni  = al($V, 'kvkk.bant_metni', 'Sitemiz reklam veya takip çerezi kullanmaz. Onay verirseniz harita ve videolar için Google ve YouTube çerezleri kullanılabilir.');
$formOnay   = al($V, 'kvkk.form_onay', "[metin]'ni okudum; mesajımda paylaştığım bilgilerin bu kapsamda işlenmesini kabul ediyorum.");

$SOSYAL = array(
    'instagram' => 'fa-instagram',
    'facebook'  => 'fa-facebook-f',
    'youtube'   => 'fa-youtube',
    'linkedin'  => 'fa-linkedin-in',
    'tiktok'    => 'fa-tiktok',
    'x'         => 'fa-x-twitter',
);
$sosyal = array();
foreach ($SOSYAL as $ag => $ikonSinifi) {
    $link = link_temizle(al($V, 'footer.' . $ag));
    if ($link !== '') {
        $sosyal[$ag] = array('link' => $link, 'ikon' => $ikonSinifi);
    }
}

/* ---------------- Menü ---------------- */

$MENU_VARSAYILAN = array(
    'anasayfa'  => 'Ana Sayfa',
    'hizmetler' => 'Hizmetler',
    'galeri'    => 'Galeri',
    'hakkinda'  => 'Hakkında',
    'blog'      => 'Blog',
    'yorumlar'  => 'Yorumlarımız',
    'sss'       => 'S.S.S.',
    'iletisim'  => 'İletişim',
    'whatsapp'  => 'Whatsapp Linki',
);
$icerikVar = array(
    'anasayfa'  => true,
    'hizmetler' => count($hizmetler) > 0,
    'galeri'    => count($galeri) > 0,
    'hakkinda'  => trim($hkMetin . al($V, 'hakkinda.isim')) !== '',
    'blog'      => count($blog) > 0,
    'yorumlar'  => count($yorumlar) > 0,
    'sss'       => count($sss) > 0,
    'iletisim'  => true,
    'whatsapp'  => $waLink !== '',
);
$menu = array();
foreach ($MENU_VARSAYILAN as $k => $varsayilanYazi) {
    $yazi = trim(al($V, 'menu.' . $k . '.yazi'));
    $menu[$k] = array(
        'yazi'   => $yazi !== '' ? $yazi : $varsayilanYazi,
        'goster' => (bool) al($V, 'menu.' . $k . '.goster', true) && $icerikVar[$k],
        'link'   => $k === 'whatsapp' ? $waLink : '#' . $k,
    );
}
$menuDolu = false; // tüm menü gizlenirse hamburger butonu ve footer linkleri de gizlenir
foreach ($menu as $m) {
    if ($m['goster']) {
        $menuDolu = true;
    }
}
$iletisimDolu = $adres !== '' || $telefon !== '' || $eposta !== '' || count($saatler) > 0;

function bolum_acik($k)
{
    global $menu;
    // Ana Sayfa ve WhatsApp sadece menüden gizlenir, bölümler her zaman görünür
    return $k === 'anasayfa' || $menu[$k]['goster'];
}

$ogGorsel = '';
if (!empty($slaytlar) && preg_match('~^https?://~i', $slaytlar[0]['gorsel'])) {
    $ogGorsel = $slaytlar[0]['gorsel'];
}

$jsAyar = json_encode(
    array('wa' => $waNumara, 'eposta' => $eposta, 'site' => $siteAdi, 'cerezBandi' => $cerezBandi),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$favicon = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="18" fill="' . $ana . '"/><path d="M46 16C30 16 18 24 18 40c0 3 .6 5.6 1.6 7.8C22 38 30 30 40 26c-8 6-14 13-17 24 2 .6 4 1 6 1 14 0 21-12 17-35z" fill="#fff"/></svg>');

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($sayfaBasligi) ?></title>
<?php if ($siteAciklama !== ''): ?>
<meta name="description" content="<?= e($siteAciklama) ?>">
<?php endif; ?>
<meta name="theme-color" content="<?= e($ana) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($sayfaBasligi) ?>">
<meta property="og:description" content="<?= e($siteAciklama) ?>">
<?php if ($ogGorsel !== ''): ?>
<meta property="og:image" content="<?= e($ogGorsel) ?>">
<?php endif; ?>
<link rel="icon" href="<?= e($favicon) ?>">
<?php if ($GORSEL_HIZLANDIR): ?>
<link rel="preconnect" href="https://wsrv.nl">
<?php endif; ?>
<?php if (!empty($slaytlar) && $slaytlar[0]['gorsel'] !== ''): $ilkHero = link_temizle($slaytlar[0]['gorsel']); ?>
<?php if (hizli_resim($ilkHero, 800) !== $ilkHero): ?>
<link rel="preload" as="image" href="<?= e(hizli_resim($ilkHero, 800)) ?>" imagesrcset="<?= e(hizli_resim($ilkHero, 480) . ' 480w, ' . hizli_resim($ilkHero, 800) . ' 800w, ' . hizli_resim($ilkHero, 1100) . ' 1100w') ?>" imagesizes="<?= e($HERO_BOYUT) ?>" fetchpriority="high">
<?php else: ?>
<link rel="preload" as="image" href="<?= e($ilkHero) ?>" fetchpriority="high">
<?php endif; ?>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..700;1,9..144,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
<script>
document.documentElement.className += ' js';
// Hızlandırılmış fotoğraf yüklenemezse orijinal dosyayı göster
function resimYedek(img) { img.onerror = null; img.removeAttribute('srcset'); img.src = img.getAttribute('data-asil'); }
</script>
<style>
:root {
    --ana: <?= $ana ?>;
    --ana-koyu: <?= $anaKoyu ?>;
    --ana-acik: <?= $anaAcik ?>;
    --ana-acik2: <?= $anaAcik2 ?>;
    --ana-rgb: <?= renk_rgb($ana) ?>;
    --koyu: <?= $koyuZemin ?>;
    --zemin: #fbfaf6;
    --zemin2: <?= $anaZemin ?>;
    --murekkep: #13261b;
    --metin: #55625a;
    --metin-acik: #86918a;
    --cizgi: #e5e9e3;
    --vurgu: #ee8f5e;
    --yildiz: #f4b33f;
    --golge: 0 30px 60px -32px rgba(19, 38, 27, .32);
    --egri: cubic-bezier(.2, .7, .2, 1);
    --baslik-font: 'Fraunces', Georgia, 'Times New Roman', serif;
    --govde-font: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
[hidden] { display: none !important; }
html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body {
    font-family: var(--govde-font);
    font-size: 16px;
    line-height: 1.7;
    color: var(--metin);
    background: var(--zemin);
    overflow-x: hidden;
    overflow-wrap: break-word;
    overflow-wrap: anywhere; /* uzun kelime / e-posta / link kutudan taşmasın */
    -webkit-font-smoothing: antialiased;
}
body.kilitli { overflow: hidden; }
img { max-width: 100%; display: block; }
a { color: inherit; text-decoration: none; }
button { font: inherit; color: inherit; background: none; border: 0; cursor: pointer; }
ul { list-style: none; }
h1, h2, h3, h4 {
    font-family: var(--baslik-font);
    color: var(--murekkep);
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -.015em;
}
h1 em, h2 em, h3 em { font-style: italic; font-weight: 500; color: var(--ana); }
:focus-visible { outline: 3px solid rgba(var(--ana-rgb), .45); outline-offset: 3px; border-radius: 6px; }
::selection { background: var(--ana); color: #fff; }

.kap { width: min(1200px, 100% - 40px); margin-inline: auto; }
section[id] { scroll-margin-top: 72px; }
.bolum { padding: 110px 0; position: relative; }
.bolum-alt { background: var(--zemin2); }

.bolum-bas { max-width: 680px; margin: 0 auto 58px; text-align: center; }
.bolum-bas h2 { font-size: clamp(2.05rem, 1.3rem + 2.6vw, 3.15rem); }
.bolum-bas p { margin-top: 16px; font-size: 1.06rem; }
.bolum-bas.sol { text-align: left; margin-left: 0; }

.ust-baslik {
    display: inline-flex; align-items: center; gap: 12px;
    font-size: 12.5px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase;
    color: var(--ana); margin-bottom: 16px;
}
.ust-baslik::before, .ust-baslik::after {
    content: ""; width: 26px; height: 2px; border-radius: 2px; background: currentColor; opacity: .45;
}
.sol .ust-baslik::after, .ust-baslik.sol::after { display: none; }

/* ---------- Butonlar ---------- */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    padding: 15px 28px; border-radius: 999px;
    font-weight: 700; font-size: 15px; line-height: 1.2; white-space: nowrap;
    transition: transform .25s var(--egri), box-shadow .25s, background .25s, color .25s;
}
.btn:hover { transform: translateY(-2px); }
.btn-ana { background: var(--ana); color: #fff; box-shadow: 0 12px 26px -10px rgba(var(--ana-rgb), .6); }
.btn-ana:hover { background: var(--ana-koyu); box-shadow: 0 16px 30px -10px rgba(var(--ana-rgb), .65); }
.btn-cizgi { background: #fff; color: var(--murekkep); box-shadow: inset 0 0 0 1.5px var(--cizgi); }
.btn-cizgi:hover { color: var(--ana); box-shadow: inset 0 0 0 1.5px var(--ana); }
.btn-beyaz { background: #fff; color: var(--murekkep); }
.btn-beyaz:hover { background: var(--ana-acik); }
.btn i { font-size: 1.05em; }

/* ---------- Üst menü ---------- */
.ust {
    position: fixed; inset: 0 0 auto; z-index: 50;
    padding: 18px 0;
    transition: padding .35s var(--egri), background .35s, box-shadow .35s;
}
.ust.kaydi {
    padding: 10px 0;
    background: rgba(251, 250, 246, .88);
    -webkit-backdrop-filter: saturate(1.6) blur(14px);
    backdrop-filter: saturate(1.6) blur(14px);
    box-shadow: 0 1px 0 rgba(19, 38, 27, .06), 0 12px 30px -20px rgba(19, 38, 27, .3);
}
.ust-ic { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.logo { display: inline-flex; align-items: center; gap: 12px; flex: 0 1 auto; min-width: 0; }
.logo-ikon {
    width: 44px; height: 44px; flex: 0 0 44px;
    display: grid; place-items: center;
    border-radius: 16px 5px 16px 5px;
    background: var(--ana); color: #fff; font-size: 18px;
    box-shadow: 0 8px 18px -8px rgba(var(--ana-rgb), .8);
}
.logo-yazi { font-family: var(--baslik-font); font-size: 1.45rem; font-weight: 600; color: var(--murekkep); letter-spacing: -.02em; line-height: 1.15;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; } /* çok uzun site adı en fazla 2 satır */

.menu {
    display: flex; align-items: center; gap: 2px; flex: 0 1 auto; min-width: 0;
    overflow: hidden; padding: 8px 6px 16px; margin: -8px -6px -16px; /* sığmazsa JS hamburger menüye geçirir */
}
.menu a {
    position: relative; padding: 8px 11px; white-space: nowrap;
    font-size: 14.5px; font-weight: 600; color: var(--murekkep);
    transition: color .2s;
}
.menu a::after {
    content: ""; position: absolute; left: 11px; right: 11px; bottom: 2px; height: 2px;
    border-radius: 2px; background: var(--ana);
    transform: scaleX(0); transition: transform .3s var(--egri);
}
.menu a:hover, .menu a.aktif { color: var(--ana); }
.menu a:hover::after, .menu a.aktif::after { transform: scaleX(1); }
.menu a.menu-wa {
    margin-left: 10px; padding: 10px 18px; border-radius: 999px;
    background: var(--ana); color: #fff;
    display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 10px 22px -10px rgba(var(--ana-rgb), .7);
    transition: background .25s, transform .25s;
}
.menu a.menu-wa::after { display: none; }
.menu a.menu-wa:hover { background: var(--ana-koyu); color: #fff; transform: translateY(-1px); }
.menu a.menu-wa i { font-size: 17px; }

.menu-ac {
    display: none; width: 48px; height: 48px; border-radius: 16px; flex-shrink: 0;
    background: #fff; box-shadow: inset 0 0 0 1.5px var(--cizgi);
    flex-direction: column; align-items: center; justify-content: center; gap: 5px;
}
.menu-ac span { width: 20px; height: 2px; border-radius: 2px; background: var(--murekkep); transition: transform .3s; }
.menu-ac span:nth-child(2) { width: 14px; margin-left: 6px; }
/* Telefonda üst menüde her zaman görünen arama ikonu */
.ust-ara { display: none; }
@media (max-width: 900px) {
    .ust-ara {
        display: grid; place-items: center; flex: 0 0 48px; width: 48px; height: 48px;
        margin-left: auto; margin-right: -10px; border-radius: 16px;
        background: var(--ana); color: #fff; font-size: 18px;
        box-shadow: 0 10px 22px -10px rgba(var(--ana-rgb), .8);
    }
}

/* Mobil çekmece menü */
.cekmece { position: fixed; inset: 0; z-index: 70; visibility: hidden; transition: visibility 0s .45s; }
.cekmece.acik { visibility: visible; transition: none; }
.cekmece-arka {
    position: absolute; inset: 0; background: rgba(10, 24, 16, .5);
    opacity: 0; transition: opacity .4s;
}
.cekmece.acik .cekmece-arka { opacity: 1; }
.cekmece-panel {
    position: absolute; top: 0; right: 0; height: 100%; width: min(88vw, 390px);
    background: var(--zemin); display: flex; flex-direction: column;
    padding: 18px 24px calc(26px + env(safe-area-inset-bottom));
    overflow-y: auto; transform: translateX(100%);
    transition: transform .45s var(--egri);
}
.cekmece.acik .cekmece-panel { transform: none; }
.cekmece-ust { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.cekmece-kapat {
    width: 46px; height: 46px; border-radius: 14px; background: #fff;
    box-shadow: inset 0 0 0 1.5px var(--cizgi); font-size: 20px; color: var(--murekkep);
    display: grid; place-items: center;
}
.cekmece nav a {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 2px; border-bottom: 1px solid var(--cizgi);
    font-family: var(--baslik-font); font-size: 1.32rem; font-weight: 500; color: var(--murekkep);
    opacity: 0; transform: translateX(24px);
    transition: opacity .4s, transform .4s var(--egri), color .2s;
}
.cekmece nav a .cn { font-family: var(--govde-font); font-size: 12px; font-weight: 700; color: var(--metin-acik); width: 22px; }
.cekmece nav a .fa-arrow-right { margin-left: auto; font-size: 13px; color: var(--ana); opacity: 0; transform: translateX(-6px); transition: .25s; }
.cekmece nav a.aktif, .cekmece nav a:hover { color: var(--ana); }
.cekmece nav a.aktif .fa-arrow-right, .cekmece nav a:hover .fa-arrow-right { opacity: 1; transform: none; }
.cekmece.acik nav a { opacity: 1; transform: none; }
.cekmece-alt { margin-top: auto; padding-top: 26px; display: grid; gap: 12px; }
.cekmece-alt .btn { width: 100%; }
.cekmece-iletisim { text-align: center; font-size: 14px; font-weight: 600; color: var(--metin); }

/* ---------- HERO ---------- */
.hero {
    position: relative; overflow: hidden;
    min-height: 100vh; min-height: 100svh;
    display: flex; align-items: center;
    padding: 120px 0 70px;
}
.hero-zemin { position: absolute; inset: 0; pointer-events: none; }
.leke { position: absolute; border-radius: 50%; filter: blur(8px); }
.leke.l1 {
    width: 760px; height: 760px; top: -260px; right: -220px;
    background: radial-gradient(circle, rgba(var(--ana-rgb), .2), rgba(var(--ana-rgb), 0) 66%);
}
.leke.l2 {
    width: 620px; height: 620px; bottom: -60px; left: -240px;
    background: radial-gradient(circle, rgba(238, 143, 94, .18), rgba(238, 143, 94, 0) 66%);
}
.noktalar {
    position: absolute; top: 110px; left: 0; width: 46%; height: 70%;
    background-image: radial-gradient(rgba(var(--ana-rgb), .2) 1.2px, transparent 1.4px);
    background-size: 22px 22px;
    -webkit-mask-image: radial-gradient(ellipse at 20% 40%, #000 10%, transparent 65%);
    mask-image: radial-gradient(ellipse at 20% 40%, #000 10%, transparent 65%);
}
.hero > .kap { position: relative; }
.hero-slaytlar { display: grid; }
.hero-slayt {
    grid-area: 1 / 1;
    display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr); gap: 60px; align-items: center;
    opacity: 0; visibility: hidden;
    transition: opacity .7s ease, visibility 0s .7s;
}
.hero-slayt.aktif { opacity: 1; visibility: visible; transition: opacity .7s ease; }

.hero-metin { max-width: 590px; }
.hero-metin > * {
    opacity: 0; transform: translateY(26px);
    transition: opacity .35s ease, transform .35s ease;
}
.hero-slayt.aktif .hero-metin > * {
    opacity: 1; transform: none;
    transition: opacity .8s ease, transform .8s var(--egri);
}
.hero-slayt.aktif .hero-metin > :nth-child(1) { transition-delay: .15s; }
.hero-slayt.aktif .hero-metin > :nth-child(2) { transition-delay: .25s; }
.hero-slayt.aktif .hero-metin > :nth-child(3) { transition-delay: .37s; }
.hero-slayt.aktif .hero-metin > :nth-child(4) { transition-delay: .49s; }

.hero-rozet {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 7px 16px 7px 7px; margin-bottom: 24px;
    border-radius: 999px; background: #fff;
    box-shadow: 0 1px 0 rgba(19, 38, 27, .04), 0 10px 24px -14px rgba(19, 38, 27, .35);
    font-size: 13.5px; font-weight: 700; color: var(--murekkep);
}
.hero-rozet i {
    width: 30px; height: 30px; border-radius: 50%;
    display: grid; place-items: center;
    background: var(--ana-acik); color: var(--ana); font-size: 13px;
}
.hero-baslik {
    font-size: clamp(2.6rem, 1.2rem + 4.4vw, 4.8rem);
    line-height: 1.03; letter-spacing: -.03em; font-weight: 600;
}
.hero-aciklama { margin-top: 22px; font-size: 1.16rem; max-width: 480px; }
.hero-butonlar { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 36px; }

.hero-gorsel { position: relative; justify-self: end; width: min(100%, 460px); margin-right: 24px; }
.hero-cerceve {
    position: relative; z-index: 1;
    aspect-ratio: 4 / 5; overflow: hidden;
    border-radius: 999px 999px 36px 36px;
    background: var(--ana-acik);
    box-shadow: 0 50px 90px -40px rgba(19, 38, 27, .55);
}
.hero-cerceve img {
    width: 100%; height: 100%; object-fit: cover;
    transform: scale(1.14);
    transition: transform 7s var(--egri);
}
.hero-slayt.aktif .hero-cerceve img { transform: scale(1); }
.hero-halka {
    position: absolute; inset: -20px -24px 24px 24px; z-index: 0;
    border-radius: 999px 999px 40px 40px;
    border: 1.5px solid rgba(var(--ana-rgb), .35);
}
.hero-daire {
    position: absolute; z-index: 0; left: -46px; bottom: 90px;
    width: 120px; height: 120px; border-radius: 50%;
    background: rgba(238, 143, 94, .9);
}
.hero-kart {
    position: absolute; z-index: 2;
    display: flex; align-items: center; gap: 12px;
    padding: 12px 18px 12px 12px; border-radius: 20px;
    background: rgba(255, 255, 255, .94);
    -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
    box-shadow: 0 24px 40px -20px rgba(19, 38, 27, .45);
    opacity: 0; transform: translateY(16px) scale(.94);
    transition: opacity .5s, transform .5s var(--egri);
}
.hero-slayt.aktif .hero-kart { opacity: 1; transform: none; transition: opacity .8s .6s, transform .8s .6s var(--egri); }
.hero-slayt.aktif .hero-kart.k2 { transition-delay: .8s; }
.hero-kart.k1 { left: -60px; bottom: 54px; }
.hero-kart.k2 { right: -40px; top: 78px; }
.hero-kart .hk-i {
    width: 44px; height: 44px; border-radius: 14px; flex: 0 0 44px;
    display: grid; place-items: center; font-size: 18px;
    background: var(--ana-acik); color: var(--ana);
}
.hero-kart.k2 .hk-i { background: #fff3e2; color: var(--yildiz); }
.hero-kart strong { display: block; font-family: var(--baslik-font); font-size: 1.4rem; line-height: 1.1; color: var(--murekkep); }
.hero-kart small { display: block; font-size: 12.5px; font-weight: 600; color: var(--metin); }
.hero-kart .yuz { animation: yuz 6s ease-in-out infinite; display: flex; align-items: center; gap: 12px; }
.hero-kart.k2 .yuz { animation-delay: -3s; }
@keyframes yuz { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

.hero-kontrol { display: flex; align-items: center; gap: 22px; margin-top: 56px; }
.hero-ok {
    width: 50px; height: 50px; border-radius: 50%; flex: 0 0 50px;
    display: grid; place-items: center; font-size: 15px;
    background: #fff; color: var(--murekkep);
    box-shadow: inset 0 0 0 1.5px var(--cizgi);
    transition: background .25s, color .25s, box-shadow .25s;
}
.hero-ok:hover { background: var(--ana); color: #fff; box-shadow: none; }
.hero-noktalar { display: flex; align-items: center; gap: 16px; }
.hero-nokta { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 13px; font-weight: 800; color: var(--metin-acik); transition: color .3s; }
.hero-nokta.aktif { color: var(--murekkep); }
.hero-nokta .cubuk { position: relative; width: 46px; height: 3px; border-radius: 3px; background: rgba(19, 38, 27, .12); overflow: hidden; }
.hero-nokta .dolum { position: absolute; inset: 0; background: var(--ana); transform: scaleX(0); transform-origin: left; }
.hero-nokta.aktif .dolum { animation: dolum 6.5s linear forwards; }
.hero.durdu .hero-nokta.aktif .dolum { animation-play-state: paused; }
@keyframes dolum { to { transform: scaleX(1); } }

/* ---------- Hizmetler ---------- */
.hizmet-izgara { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
.hizmet-kart {
    position: relative; overflow: hidden;
    background: #fff; border: 1px solid var(--cizgi); border-radius: 28px;
    padding: 36px 32px 34px;
    transition: transform .4s var(--egri), box-shadow .4s, border-color .4s;
}
.hizmet-kart::after {
    content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 3px;
    background: var(--ana); transform: scaleX(0); transform-origin: left;
    transition: transform .5s var(--egri);
}
.hizmet-kart:hover { transform: translateY(-6px); box-shadow: var(--golge); border-color: transparent; }
.hizmet-kart:hover::after { transform: scaleX(1); }
.hizmet-no {
    position: absolute; top: 26px; right: 30px;
    font-family: var(--baslik-font); font-size: 2.3rem; font-weight: 600; line-height: 1;
    color: rgba(var(--ana-rgb), .1);
}
.hizmet-ikon {
    width: 66px; height: 66px; margin-bottom: 26px;
    display: grid; place-items: center;
    border-radius: 22px 8px 22px 8px;
    background: var(--ana-acik); color: var(--ana); font-size: 26px;
    transition: background .4s, color .4s, transform .4s var(--egri);
}
.hizmet-kart:hover .hizmet-ikon { background: var(--ana); color: #fff; transform: rotate(-8deg); }
.hizmet-kart h3 { font-size: 1.42rem; margin-bottom: 10px; }
.hizmet-kart p { font-size: .98rem; }

/* ---------- VKİ hesaplama ---------- */
.vki { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 70px; align-items: center; }
.vki .bolum-bas { margin-bottom: 30px; }
.vki-aralik { display: grid; gap: 10px; }
.vki-aralik li {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 18px; border-radius: 16px;
    background: #fff; border: 1.5px solid var(--cizgi);
    font-weight: 700; color: var(--murekkep); font-size: .97rem;
    transition: border-color .3s, box-shadow .3s, transform .3s var(--egri);
}
.vki-aralik li::before { content: ""; width: 12px; height: 12px; flex: 0 0 12px; border-radius: 50%; background: var(--r); }
.vki-aralik li b { margin-left: auto; font-weight: 600; font-size: .92rem; color: var(--metin); }
.vki-aralik li.aktif { border-color: var(--r); transform: translateX(6px); box-shadow: 0 14px 30px -20px rgba(19, 38, 27, .45); }
.vki-kart { padding: 38px; box-shadow: var(--golge); border-color: transparent; }
.vki-alan + .vki-alan { margin-top: 26px; }
.vki-alan-ust { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.vki-alan-ust label { font-weight: 700; color: var(--murekkep); }
.vki-giris {
    display: inline-flex; align-items: baseline; gap: 6px;
    padding: 4px 14px; border-radius: 14px;
    background: var(--zemin); border: 1.5px solid var(--cizgi);
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.vki-giris:focus-within { background: #fff; border-color: var(--ana); box-shadow: 0 0 0 4px rgba(var(--ana-rgb), .12); }
.vki-giris input {
    width: 3.4ch; border: 0; background: none; outline: none; text-align: right;
    font-family: var(--baslik-font); font-size: 1.65rem; font-weight: 600; color: var(--murekkep);
    -moz-appearance: textfield; appearance: textfield;
}
.vki-giris input::-webkit-outer-spin-button, .vki-giris input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.vki-giris span { font-size: .9rem; font-weight: 700; color: var(--metin-acik); }
.vki-kaydirac {
    -webkit-appearance: none; appearance: none; display: block;
    width: 100%; height: 8px; border-radius: 999px; cursor: pointer; outline: none;
    background: linear-gradient(to right, var(--ana) var(--dolu, 50%), var(--cizgi) var(--dolu, 50%));
}
.vki-kaydirac::-webkit-slider-thumb {
    -webkit-appearance: none; width: 28px; height: 28px; border-radius: 50%;
    background: #fff; border: 3px solid var(--ana); box-shadow: 0 4px 12px -2px rgba(19, 38, 27, .35);
    transition: transform .15s;
}
.vki-kaydirac::-moz-range-thumb {
    width: 22px; height: 22px; border-radius: 50%;
    background: #fff; border: 3px solid var(--ana); box-shadow: 0 4px 12px -2px rgba(19, 38, 27, .35);
}
.vki-kaydirac:active::-webkit-slider-thumb { transform: scale(1.12); }
.vki-kaydirac:focus-visible { outline: 3px solid rgba(var(--ana-rgb), .4); outline-offset: 8px; }
.vki-sonuc { margin-top: 30px; padding: 24px 24px 20px; border-radius: 22px; background: var(--zemin2); }
.vki-sonuc-ust { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.vki-sonuc-ust small { display: block; margin-bottom: 4px; font-size: 13px; font-weight: 700; color: var(--metin-acik); }
.vki-sayi { font-family: var(--baslik-font); font-size: 3.1rem; font-weight: 600; line-height: 1; color: var(--murekkep); letter-spacing: -.02em; }
.vki-durum { padding: 8px 15px; border-radius: 999px; background: var(--r, var(--ana)); color: #fff; font-size: 13.5px; font-weight: 800; white-space: nowrap; transition: background .3s; }
.vki-cubuk {
    position: relative; height: 12px; border-radius: 999px;
    background: linear-gradient(to right, #4ea3e0 0 11.67%, #2f9e5b 11.67% 33.33%, #e0a020 33.33% 50%, #ef7d3c 50% 83.33%, #d9443a 83.33% 100%);
}
.vki-isaret {
    position: absolute; top: 50%; left: var(--konum, 30%);
    width: 26px; height: 26px; margin: -13px 0 0 -13px; border-radius: 50%;
    background: #fff; border: 5px solid var(--murekkep); box-shadow: 0 4px 10px rgba(0, 0, 0, .25);
    transition: left .5s var(--egri);
}
.vki-olcek { position: relative; height: 18px; margin-top: 10px; font-size: 12px; font-weight: 700; color: var(--metin-acik); }
.vki-olcek span { position: absolute; transform: translateX(-50%); }
.vki-yorum { margin-top: 12px; font-size: .97rem; min-height: 3.4em; }
.vki-yorum b { color: var(--murekkep); }
.vki-btn { width: 100%; margin-top: 22px; }
.vki-not { display: flex; gap: 8px; margin-top: 14px; font-size: 12.5px; line-height: 1.5; color: var(--metin-acik); }
.vki-not i { margin-top: 3px; }

/* ---------- İstatistikler ---------- */
.istatistik { position: relative; overflow: hidden; background: var(--koyu); padding: 96px 0; color: #fff; }
.istatistik::before {
    content: ""; position: absolute; width: 700px; height: 700px; top: -360px; left: -160px; border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--ana-rgb), .55), rgba(var(--ana-rgb), 0) 68%);
}
.istatistik::after {
    content: "\f06c"; font-family: "Font Awesome 6 Free"; font-weight: 900;
    position: absolute; right: -40px; bottom: -90px; font-size: 300px; line-height: 1;
    color: rgba(255, 255, 255, .035); transform: rotate(-18deg);
}
.istatistik .kap { position: relative; z-index: 1; }
.ist-baslik { text-align: center; color: #fff; font-size: clamp(1.8rem, 1.2rem + 1.9vw, 2.6rem); margin-bottom: 60px; }
.ist-baslik em { color: var(--ana-acik2); }
.ist-izgara { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
.ist-oge { position: relative; text-align: center; padding: 6px 16px; }
.ist-oge + .ist-oge::before {
    content: ""; position: absolute; left: 0; top: 12%; height: 76%; width: 1px; background: rgba(255, 255, 255, .14);
}
.ist-sayi { font-family: var(--baslik-font); font-size: clamp(2.7rem, 1.6rem + 3vw, 4.2rem); font-weight: 600; line-height: 1; color: #fff; letter-spacing: -.03em; }
.ist-sayi .ek { color: var(--ana-acik2); font-weight: 500; }
.ist-etiket { margin-top: 14px; color: rgba(255, 255, 255, .72); font-weight: 600; font-size: 1rem; }

/* ---------- Galeri ---------- */
.galeri-filtre { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin: -22px 0 38px; }
.galeri-filtre button {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 999px;
    background: #fff; box-shadow: inset 0 0 0 1.5px var(--cizgi);
    font-size: 14px; font-weight: 700; color: var(--murekkep);
    transition: background .25s, color .25s, box-shadow .25s;
}
.galeri-filtre button .say { font-size: 12px; opacity: .55; }
.galeri-filtre button:hover { box-shadow: inset 0 0 0 1.5px var(--ana); color: var(--ana); }
.galeri-filtre button.aktif { background: var(--murekkep); color: #fff; box-shadow: none; }
.galeri-izgara { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.galeri-oge {
    position: relative; display: block; isolation: isolate;
    aspect-ratio: 16 / 10; overflow: hidden; border-radius: 22px;
    background: var(--ana-acik);
}
.galeri-oge.giris { animation: giris .5s var(--egri) both; }
@keyframes giris { from { opacity: 0; transform: scale(.95); } }
.galeri-oge img { width: 100%; height: 100%; object-fit: cover; object-position: center 30%; transition: transform .8s var(--egri); }
.galeri-oge.video img { object-position: center; transform: scale(1.12); } /* YouTube kapak resmindeki siyah bantları gizler */
.galeri-oge:hover img { transform: scale(1.07); }
.galeri-oge.video:hover img { transform: scale(1.19); }
.galeri-oge::after {
    content: ""; position: absolute; inset: 0; z-index: 1;
    background: linear-gradient(180deg, rgba(10, 24, 16, 0) 40%, rgba(10, 24, 16, .72));
    opacity: 0; transition: opacity .4s;
}
.galeri-oge.video::after { opacity: .45; }
.galeri-oge:hover::after { opacity: 1; }
.go-oynat {
    position: absolute; z-index: 2; left: 50%; top: 50%;
    width: 62px; height: 62px; margin: -31px 0 0 -31px; border-radius: 50%;
    display: grid; place-items: center; padding-left: 4px;
    background: rgba(255, 255, 255, .94); color: var(--ana); font-size: 19px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, .28);
    transition: transform .35s var(--egri);
}
.go-oynat::before {
    content: ""; position: absolute; inset: -9px; border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, .7);
    animation: nabiz 2.4s ease-out infinite;
}
@keyframes nabiz { from { transform: scale(.85); opacity: 1; } to { transform: scale(1.35); opacity: 0; } }
.galeri-oge:hover .go-oynat { transform: scale(1.1); }
.go-buyut {
    position: absolute; z-index: 2; right: 14px; top: 14px;
    width: 42px; height: 42px; border-radius: 14px;
    display: grid; place-items: center;
    background: rgba(255, 255, 255, .94); color: var(--murekkep); font-size: 15px;
    opacity: 0; transform: translateY(-8px); transition: opacity .35s, transform .35s var(--egri);
}
.galeri-oge:hover .go-buyut { opacity: 1; transform: none; }
.go-rozet {
    position: absolute; z-index: 2; left: 14px; top: 14px;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 11px; border-radius: 999px;
    background: rgba(10, 24, 16, .5); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
    color: #fff; font-size: 12px; font-weight: 700;
}
.go-baslik {
    position: absolute; z-index: 2; left: 18px; right: 18px; bottom: 14px;
    color: #fff; font-weight: 700; font-size: 14.5px; line-height: 1.35;
    opacity: 0; transform: translateY(10px); transition: opacity .35s, transform .35s var(--egri);
}
.galeri-oge:hover .go-baslik { opacity: 1; transform: none; }

/* Işık kutusu (lightbox) */
.isik {
    position: fixed; inset: 0; z-index: 100;
    display: flex; align-items: center; justify-content: center;
    background: rgba(7, 15, 10, .95);
    opacity: 0; transition: opacity .3s;
    touch-action: pan-y;
}
.isik.acik { opacity: 1; }
.isik-ust {
    position: absolute; top: 0; left: 0; right: 0; z-index: 2;
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; color: rgba(255, 255, 255, .8); font-weight: 700; font-size: 14px;
}
.isik-btn {
    width: 52px; height: 52px; border-radius: 50%;
    display: grid; place-items: center; font-size: 20px; color: #fff;
    background: rgba(255, 255, 255, .1); transition: background .25s;
}
.isik-btn:hover { background: rgba(255, 255, 255, .22); }
.isik-ok { position: absolute; top: 50%; margin-top: -26px; z-index: 2; }
.isik-ok.onceki { left: 22px; }
.isik-ok.sonraki { right: 22px; }
.isik-icerik { display: flex; flex-direction: column; align-items: center; padding: 0 90px; }
.isik-medya { animation: giris .4s var(--egri) both; }
.isik-medya img { max-width: min(92vw, 1200px); max-height: 80vh; border-radius: 16px; object-fit: contain; }
.isik-video { width: min(88vw, 1100px, calc(80vh * 16 / 9)); aspect-ratio: 16 / 9; border-radius: 16px; overflow: hidden; background: #000; }
.isik-video iframe { width: 100%; height: 100%; border: 0; display: block; }
.isik-baslik { margin-top: 16px; color: rgba(255, 255, 255, .85); text-align: center; font-weight: 600; min-height: 1.5em; }
/* Çerez onayı yokken video yerine gösterilen kart */
.isik-riza { position: relative; display: grid; place-items: center; background-size: cover; background-position: center; }
.isik-riza::before { content: ""; position: absolute; inset: 0; background: rgba(7, 15, 10, .78); }
.isik-riza-ic { position: relative; max-width: 440px; padding: 24px; text-align: center; color: #fff; }
.isik-riza-ic > i { font-size: 44px; color: #ff3d3d; }
.isik-riza-ic p { margin: 12px 0 20px; font-weight: 600; line-height: 1.55; color: rgba(255, 255, 255, .9); }
.isik-riza-btn { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
.isik-riza-btn .btn { padding: 12px 20px; font-size: 14px; }

/* ---------- Hakkında ---------- */
.hakkinda { display: grid; grid-template-columns: minmax(0, .92fr) minmax(0, 1.08fr); gap: 90px; align-items: center; }
.hk-gorsel { position: relative; width: 100%; max-width: 450px; justify-self: center; }
.hk-cerceve {
    position: relative; z-index: 1;
    aspect-ratio: 4 / 5; overflow: hidden; border-radius: 36px;
    background: var(--ana-acik); box-shadow: var(--golge);
}
.hk-cerceve img { width: 100%; height: 100%; object-fit: cover; }
.hk-dekor {
    position: absolute; z-index: 0; left: -28px; bottom: -28px; width: 72%; height: 64%;
    border-radius: 36px;
    background-image: radial-gradient(rgba(var(--ana-rgb), .35) 1.4px, transparent 1.6px);
    background-size: 16px 16px;
}
.hk-rozet {
    position: absolute; z-index: 2; right: -30px; bottom: 46px;
    display: flex; align-items: center; gap: 14px;
    padding: 20px 24px; border-radius: 26px; max-width: 82%;
    background: var(--ana); color: #fff;
    box-shadow: 0 24px 40px -18px rgba(var(--ana-rgb), .8);
}
.hk-rozet strong { font-family: var(--baslik-font); font-size: 2.6rem; font-weight: 600; line-height: 1; }
.hk-rozet span { font-size: 13.5px; font-weight: 700; line-height: 1.35; max-width: 92px; }
.hk-metin h2 { font-size: clamp(2.2rem, 1.4rem + 2.6vw, 3.3rem); }
.hk-unvan { margin: 8px 0 24px; color: var(--ana); font-weight: 700; font-size: 1.05rem; }
.hk-yazi { font-size: 1.04rem; }
.hk-yazi p + p { margin-top: 14px; }
.hk-liste { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px 22px; margin: 30px 0 36px; }
.hk-liste li { display: flex; align-items: flex-start; gap: 12px; font-weight: 600; color: var(--murekkep); font-size: .97rem; line-height: 1.5; }
.hk-liste i {
    width: 24px; height: 24px; flex: 0 0 24px; margin-top: 1px; border-radius: 50%;
    display: grid; place-items: center; font-size: 11px;
    background: var(--ana); color: #fff;
}
.hk-butonlar { display: flex; flex-wrap: wrap; gap: 14px; }

/* ---------- Blog ---------- */
.blog-izgara { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 26px; }
.blog-kart {
    display: flex; flex-direction: column; overflow: hidden;
    background: #fff; border: 1px solid var(--cizgi); border-radius: 28px;
    transition: transform .4s var(--egri), box-shadow .4s;
}
.blog-kart:hover { transform: translateY(-6px); box-shadow: var(--golge); }
.blog-gorsel {
    position: relative; width: 100%; aspect-ratio: 16 / 10; overflow: hidden;
    display: grid; place-items: center;
    background: var(--ana-acik);
}
.blog-gorsel img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s var(--egri); }
.blog-kart:hover .blog-gorsel img { transform: scale(1.06); }
.blog-gorsel.ikonlu::before, .blog-gorsel.ikonlu::after {
    content: ""; position: absolute; border-radius: 50%; border: 1.5px solid rgba(var(--ana-rgb), .18);
}
.blog-gorsel.ikonlu::before { width: 260px; height: 260px; }
.blog-gorsel.ikonlu::after { width: 380px; height: 380px; }
.blog-kart:nth-of-type(3n+2) .blog-gorsel.ikonlu { background: #fdeee3; }
.blog-kart:nth-of-type(3n+3) .blog-gorsel.ikonlu { background: #e7f0ee; }
.bg-ikon {
    position: relative; z-index: 1;
    width: 96px; height: 96px; border-radius: 32px 10px 32px 10px;
    display: grid; place-items: center; font-size: 38px;
    background: #fff; color: var(--ana);
    box-shadow: 0 20px 40px -20px rgba(19, 38, 27, .4);
    transform: rotate(-6deg); transition: transform .5s var(--egri);
}
.blog-kart:nth-of-type(3n+2) .bg-ikon { color: #e0763f; }
.blog-kart:nth-of-type(3n+3) .bg-ikon { color: #2e7d73; }
.blog-kart:hover .bg-ikon { transform: rotate(0) scale(1.06); }
.blog-tarih {
    position: absolute; z-index: 2; left: 16px; top: 16px;
    padding: 6px 13px; border-radius: 999px;
    background: rgba(255, 255, 255, .94); color: var(--murekkep);
    font-size: 12.5px; font-weight: 700;
}
.blog-govde { display: flex; flex-direction: column; flex: 1; padding: 26px 28px 30px; }
.blog-govde h3 { font-size: 1.34rem; line-height: 1.25; margin-bottom: 12px; }
.blog-govde h3 a { transition: color .2s; }
.blog-govde h3 a:hover { color: var(--ana); }
.blog-govde p { flex: 1; font-size: .98rem; }
.blog-devam { display: inline-flex; align-items: center; gap: 8px; margin-top: 22px; font-weight: 700; color: var(--ana); align-self: flex-start; }
.blog-devam i { transition: transform .3s var(--egri); }
.blog-kart:hover .blog-devam i { transform: translateX(5px); }

/* Blog yazısı penceresi */
.modal { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 24px; }
.modal-arka {
    position: absolute; inset: 0; background: rgba(10, 24, 16, .55);
    -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
    opacity: 0; transition: opacity .35s;
}
.modal-kutu {
    position: relative; display: flex; flex-direction: column;
    width: min(780px, 100%); max-height: calc(100vh - 48px); max-height: calc(100dvh - 48px);
    background: var(--zemin); border-radius: 30px; overflow: hidden;
    box-shadow: 0 40px 100px -30px rgba(0, 0, 0, .5);
    opacity: 0; transform: translateY(30px) scale(.98);
    transition: opacity .35s, transform .45s var(--egri);
}
.modal.acik .modal-arka { opacity: 1; }
.modal.acik .modal-kutu { opacity: 1; transform: none; }
.modal-kapat {
    position: absolute; z-index: 2; top: 16px; right: 16px;
    width: 46px; height: 46px; border-radius: 50%;
    display: grid; place-items: center; font-size: 19px;
    background: #fff; color: var(--murekkep); box-shadow: 0 6px 18px -6px rgba(0, 0, 0, .25);
}
.modal-icerik { overflow-y: auto; padding: 52px 56px 48px; overscroll-behavior: contain; }
.yazi-ust { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; font-size: 14px; font-weight: 600; color: var(--metin-acik); }
.yazi-ust .yi {
    width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center;
    background: var(--ana-acik); color: var(--ana); font-size: 18px;
}
.yazi h2 { font-size: clamp(1.8rem, 1.3rem + 1.8vw, 2.5rem); margin-bottom: 24px; padding-right: 40px; }
.yazi-gorsel { border-radius: 22px; overflow: hidden; margin-bottom: 28px; aspect-ratio: 16 / 8; }
.yazi-gorsel img { width: 100%; height: 100%; object-fit: cover; }
.yazi-govde { font-size: 1.06rem; color: #3f4c44; }
.yazi-govde p + p { margin-top: 16px; }
.yazi-govde strong { color: var(--murekkep); }
.yazi-cta {
    display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;
    margin-top: 36px; padding: 24px 26px; border-radius: 22px; background: var(--ana-acik);
}
.yazi-cta p { font-family: var(--baslik-font); font-size: 1.2rem; font-weight: 600; color: var(--murekkep); line-height: 1.3; }

/* ---------- Yorumlar ---------- */
.yorum-ray {
    display: flex; gap: 24px;
    overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth;
    padding: 8px 4px 34px; margin: 0 -4px;
    scrollbar-width: none; -ms-overflow-style: none;
}
.yorum-ray::-webkit-scrollbar { display: none; }
.yorum-kart {
    flex: 0 0 calc((100% - 48px) / 3); scroll-snap-align: start;
    display: flex; flex-direction: column;
    background: #fff; border: 1px solid var(--cizgi); border-radius: 28px;
    padding: 32px 30px 28px;
    transition: box-shadow .4s, transform .4s var(--egri);
}
.yorum-kart:hover { box-shadow: var(--golge); transform: translateY(-4px); }
.yk-ust { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.yk-yildiz { color: var(--yildiz); font-size: 14px; display: flex; gap: 3px; }
.yk-tirnak { font-size: 36px; color: var(--ana-acik2); }
.yorum-kart blockquote { flex: 1; color: var(--murekkep); font-size: 1.01rem; line-height: 1.75; }
.yorum-kart figcaption {
    display: flex; align-items: center; gap: 14px;
    margin-top: 24px; padding-top: 20px; border-top: 1px dashed var(--cizgi);
}
.yk-avatar {
    width: 48px; height: 48px; flex: 0 0 48px; border-radius: 50%;
    display: grid; place-items: center;
    font-family: var(--baslik-font); font-size: 1.25rem; font-weight: 600;
    background: var(--ana-acik); color: var(--ana);
}
.yorum-kart:nth-child(3n+2) .yk-avatar { background: #fdeee3; color: #d8692f; }
.yorum-kart:nth-child(3n+3) .yk-avatar { background: #e7f0ee; color: #2e7d73; }
.yorum-kart figcaption strong { display: block; color: var(--murekkep); font-size: 1rem; }
.yorum-kart figcaption small { color: var(--metin-acik); font-weight: 600; font-size: 13px; }
.yorum-kontrol { display: flex; align-items: center; justify-content: center; gap: 20px; }
.yorum-noktalar { display: flex; gap: 8px; }
.yorum-noktalar button { width: 9px; height: 9px; border-radius: 999px; background: rgba(19, 38, 27, .15); transition: width .35s var(--egri), background .35s; }
.yorum-noktalar button.aktif { width: 30px; background: var(--ana); }

/* ---------- S.S.S. ---------- */
.sss { display: grid; grid-template-columns: minmax(0, .82fr) minmax(0, 1.18fr); gap: 70px; align-items: start; }
.sss-sol { position: sticky; top: 110px; }
.sss-sol .bolum-bas { margin-bottom: 30px; }
.sss-destek { position: relative; overflow: hidden; padding: 32px; border-radius: 28px; background: var(--koyu); color: rgba(255, 255, 255, .78); }
.sss-destek::after {
    content: "\f086"; font-family: "Font Awesome 6 Free"; font-weight: 900;
    position: absolute; right: -14px; bottom: -30px; font-size: 130px; color: rgba(255, 255, 255, .05);
}
.sss-destek h3 { color: #fff; font-size: 1.4rem; margin-bottom: 8px; }
.sss-destek p { margin-bottom: 22px; font-size: .97rem; }
.sss-liste { display: flex; flex-direction: column; gap: 14px; }
.sss-oge { background: #fff; border: 1px solid var(--cizgi); border-radius: 22px; transition: border-color .3s, box-shadow .3s; }
.sss-oge.acik { border-color: rgba(var(--ana-rgb), .35); box-shadow: 0 24px 44px -32px rgba(19, 38, 27, .4); }
.sss-soru {
    width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 18px;
    padding: 22px 24px 22px 28px; text-align: left;
    font-size: 1.05rem; font-weight: 700; color: var(--murekkep); line-height: 1.45;
}
.sss-isaret { position: relative; flex: 0 0 36px; height: 36px; border-radius: 50%; background: var(--ana-acik); transition: background .3s; }
.sss-isaret::before, .sss-isaret::after {
    content: ""; position: absolute; left: 50%; top: 50%; width: 12px; height: 2px; margin: -1px 0 0 -6px;
    border-radius: 2px; background: var(--ana); transition: transform .35s var(--egri), background .3s;
}
.sss-isaret::after { transform: rotate(90deg); }
.sss-oge.acik .sss-isaret { background: var(--ana); }
.sss-oge.acik .sss-isaret::before, .sss-oge.acik .sss-isaret::after { background: #fff; }
.sss-oge.acik .sss-isaret::after { transform: rotate(0); }
.sss-cevap { display: grid; grid-template-rows: 1fr; transition: grid-template-rows .4s var(--egri); }
.js .sss-oge:not(.acik) .sss-cevap { grid-template-rows: 0fr; }
.sss-cevap > div { overflow: hidden; }
.sss-cevap p { padding: 0 28px 24px; }

/* ---------- İletişim ---------- */
.iletisim { display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr); gap: 28px; align-items: start; }
.kart { background: #fff; border: 1px solid var(--cizgi); border-radius: 30px; padding: 42px; }
.il-form h3 { font-size: 1.65rem; margin-bottom: 6px; }
.il-not { font-size: .96rem; margin-bottom: 28px; }
.alan-ikili { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; }
.alan { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
.alan label { font-size: 14px; font-weight: 700; color: var(--murekkep); }
.alan label span { color: var(--vurgu); }
.alan input, .alan textarea {
    width: 100%; padding: 14px 16px;
    font: inherit; font-size: 16px; color: var(--murekkep);
    background: var(--zemin); border: 1.5px solid var(--cizgi); border-radius: 16px;
    outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
}
.alan input:focus, .alan textarea:focus { background: #fff; border-color: var(--ana); box-shadow: 0 0 0 4px rgba(var(--ana-rgb), .12); }
.alan textarea { min-height: 140px; resize: vertical; }
.il-form .btn { width: 100%; margin-top: 4px; }
.form-durum { margin-top: 14px; font-size: 14px; font-weight: 700; color: var(--ana); text-align: center; }
.il-bilgi .kart { padding: 30px 34px; }
.il-satir { display: flex; gap: 16px; padding: 18px 0; border-bottom: 1px dashed var(--cizgi); }
.il-satir:first-child { padding-top: 4px; }
.il-satir:last-of-type { border-bottom: 0; }
.il-satir .ik {
    width: 48px; height: 48px; flex: 0 0 48px; border-radius: 16px;
    display: grid; place-items: center; font-size: 18px;
    background: var(--ana-acik); color: var(--ana);
}
.il-satir h4 {
    font-family: var(--govde-font); font-size: 12px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase;
    color: var(--metin-acik); margin-bottom: 3px;
}
.il-satir p, .il-satir a { color: var(--murekkep); font-weight: 600; line-height: 1.6; }
.il-satir a:hover { color: var(--ana); }
.il-bilgi .btn { width: 100%; margin-top: 18px; }
.il-harita { position: relative; margin-top: 20px; aspect-ratio: 16 / 10; overflow: hidden; border-radius: 26px; border: 1px solid var(--cizgi); background: var(--ana-acik); }
.il-harita iframe { width: 100%; height: 100%; border: 0; display: block; }
.harita-onay {
    position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
    padding: 24px; text-align: center;
    background-image: radial-gradient(rgba(var(--ana-rgb), .16) 1.2px, transparent 1.4px); background-size: 18px 18px;
}
.harita-onay .ho-ikon { width: 56px; height: 56px; border-radius: 18px; display: grid; place-items: center; background: #fff; color: var(--ana); font-size: 22px; box-shadow: 0 10px 24px -14px rgba(19, 38, 27, .4); }
.harita-onay p { max-width: 300px; font-size: 14px; font-weight: 600; color: var(--murekkep); }
.harita-onay .btn { padding: 11px 22px; font-size: 14px; }

/* Form KVKK onay kutusu */
.onay-kutu { display: flex; align-items: flex-start; gap: 12px; margin: 2px 0 20px; cursor: pointer; font-size: 14px; line-height: 1.55; color: var(--metin); }
.onay-kutu input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.onay-isaret {
    flex: 0 0 22px; width: 22px; height: 22px; margin-top: 1px; border-radius: 7px;
    display: grid; place-items: center; font-size: 12px; color: transparent;
    background: var(--zemin); border: 1.5px solid var(--cizgi);
    transition: background .2s, border-color .2s, color .2s;
}
.onay-kutu input:checked + .onay-isaret { background: var(--ana); border-color: var(--ana); color: #fff; }
.onay-kutu input:focus-visible + .onay-isaret { outline: 3px solid rgba(var(--ana-rgb), .4); outline-offset: 2px; }
.onay-kutu.hata .onay-isaret { border-color: #c0392b; box-shadow: 0 0 0 4px rgba(192, 57, 43, .12); }
.onay-yazi a { color: var(--ana); font-weight: 700; text-decoration: underline; text-underline-offset: 2px; }

/* ---------- Alt bilgi ---------- */
.alt { position: relative; overflow: hidden; background: var(--koyu); color: rgba(255, 255, 255, .7); padding-top: 90px; }
.alt::before {
    content: ""; position: absolute; width: 640px; height: 640px; right: -240px; top: -320px; border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--ana-rgb), .45), rgba(var(--ana-rgb), 0) 68%);
}
.alt .kap { position: relative; }
.alt-izgara { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, .9fr) minmax(0, 1.1fr) minmax(0, 1.3fr); gap: 50px; }
.alt .logo-yazi { color: #fff; }
.alt-marka p { margin: 22px 0 26px; max-width: 340px; font-size: .97rem; }
.sosyal { display: flex; flex-wrap: wrap; gap: 10px; }
.sosyal a {
    width: 46px; height: 46px; border-radius: 15px;
    display: grid; place-items: center; font-size: 18px;
    background: rgba(255, 255, 255, .08); color: #fff;
    transition: background .25s, transform .25s var(--egri);
}
.sosyal a:hover { background: var(--ana); transform: translateY(-3px); }
.alt h4 { color: #fff; font-size: 1.2rem; margin-bottom: 22px; }
.alt ul { display: flex; flex-direction: column; gap: 11px; }
.alt ul a { transition: color .2s, padding .25s; }
.alt ul a:hover { color: #fff; padding-left: 4px; }
.alt-blog a { display: flex; gap: 10px; line-height: 1.5; }
.alt-blog i { font-size: 11px; margin-top: 7px; color: var(--ana-acik2); }
.alt-iletisim li { display: flex; gap: 12px; line-height: 1.55; }
.alt-iletisim i { width: 16px; margin-top: 5px; color: var(--ana-acik2); text-align: center; }
.alt-son {
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    margin-top: 70px; padding: 26px 0 calc(26px + env(safe-area-inset-bottom));
    border-top: 1px solid rgba(255, 255, 255, .1); font-size: 14px;
}
.alt-son a { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; color: #fff; }
.alt-son a i { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: rgba(255, 255, 255, .1); transition: background .25s; }
.alt-son a:hover i { background: var(--ana); }
.alt-yasal { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 20px; }
.alt-son .alt-yasal a, .alt-yasal button { font-size: 13.5px; font-weight: 600; color: rgba(255, 255, 255, .7); transition: color .2s; }
.alt-son .alt-yasal a:hover, .alt-yasal button:hover { color: #fff; }

/* ---------- Çerez bandı ---------- */
.cerez {
    position: fixed; z-index: 80; left: 22px; bottom: calc(22px + env(safe-area-inset-bottom));
    width: min(430px, calc(100% - 44px)); padding: 22px 22px 20px;
    background: #fff; border: 1px solid var(--cizgi); border-radius: 24px;
    box-shadow: 0 30px 70px -20px rgba(10, 24, 16, .45);
    opacity: 0; transform: translateY(24px); transition: opacity .4s, transform .5s var(--egri);
}
.cerez.acik { opacity: 1; transform: none; }
.cerez-ust { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.cerez-ikon { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 14px; display: grid; place-items: center; background: #fdeee3; color: #d8692f; font-size: 19px; }
.cerez h3 { font-size: 1.15rem; }
.cerez p { font-size: 14px; line-height: 1.6; }
.cerez p a { color: var(--ana); font-weight: 700; text-decoration: underline; text-underline-offset: 2px; }
.cerez-butonlar { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px; }
.cerez-butonlar .btn { padding: 12px 16px; }
.yasal .yazi-govde { font-size: 1rem; }
.yasal .yazi-govde strong { display: inline-block; margin-top: 6px; font-size: 1.05rem; }

/* ---------- Sabit WhatsApp butonu ---------- */
.wa-sabit {
    position: fixed; z-index: 45; right: 22px; bottom: calc(22px + env(safe-area-inset-bottom));
    width: 60px; height: 60px; border-radius: 50%;
    display: grid; place-items: center; font-size: 31px;
    background: #25d366; color: #fff;
    box-shadow: 0 14px 30px -8px rgba(37, 211, 102, .65);
    transition: transform .3s var(--egri), opacity .3s;
}
.wa-sabit::before {
    content: ""; position: absolute; inset: 0; z-index: -1; border-radius: 50%;
    background: #25d366; animation: wa-nabiz 2.4s ease-out infinite;
}
@keyframes wa-nabiz { from { transform: scale(1); opacity: .55; } to { transform: scale(1.6); opacity: 0; } }
.wa-sabit:hover { transform: scale(1.08); }
.wa-ipucu {
    position: absolute; right: 74px; white-space: nowrap;
    padding: 9px 15px; border-radius: 14px;
    background: #fff; color: var(--murekkep); font-size: 14px; font-weight: 700;
    box-shadow: 0 12px 30px -10px rgba(0, 0, 0, .3);
    opacity: 0; transform: translateX(8px); pointer-events: none;
    transition: opacity .3s, transform .3s var(--egri);
}
.wa-sabit:hover .wa-ipucu { opacity: 1; transform: none; }

/* Sabit "Hemen Ara" butonu: sadece telefon ve tablette görünür */
.ara-sabit { display: none; }
@media (max-width: 900px) {
    .ara-sabit {
        position: fixed; z-index: 45; right: 22px; bottom: calc(22px + env(safe-area-inset-bottom));
        width: 60px; height: 60px; border-radius: 50%;
        display: grid; place-items: center; font-size: 22px;
        background: var(--ana); color: #fff;
        box-shadow: 0 14px 30px -8px rgba(var(--ana-rgb), .7);
        transition: transform .35s var(--egri), opacity .3s;
    }
    .wa-sabit ~ .ara-sabit { bottom: calc(94px + env(safe-area-inset-bottom)); }
    .ara-sabit i { animation: zil 4s ease-in-out infinite; }
    .ara-var .alt-son { padding-bottom: calc(150px + env(safe-area-inset-bottom)); }
    /* Hero butonları ekrandayken sabit butonlar onların üstüne binmesin */
    .yuzen-gizli .wa-sabit, .yuzen-gizli .ara-sabit { opacity: 0; transform: translateY(24px) scale(.85); pointer-events: none; }
}
@keyframes zil {
    0%, 72%, 100% { transform: rotate(0); }
    76% { transform: rotate(-16deg); } 80% { transform: rotate(14deg); }
    84% { transform: rotate(-10deg); } 88% { transform: rotate(6deg); } 92% { transform: rotate(0); }
}

.veri-uyari { position: relative; z-index: 60; background: #fff3cd; color: #6b4e00; text-align: center; padding: 12px 20px; font-weight: 600; font-size: 14px; }

/* ---------- Kaydırınca beliren öğeler ---------- */
.js .reveal { opacity: 0; transform: translateY(30px); transition: opacity .9s ease, transform .9s var(--egri); transition-delay: calc(var(--s, 0) * 90ms); }
.js .reveal.gorunur { opacity: 1; transform: none; }

/* ================== DUYARLI (MOBİL) TASARIM ================== */
@media (max-width: 1160px) {
    .menu { display: none; }
    .menu-ac { display: flex; }
}
/* Menü yazıları uzun olup tek satıra sığmazsa geniş ekranda da hamburger menü */
.ust.sigmiyor .menu { display: none; }
.ust.sigmiyor .menu-ac { display: flex; }
@media (max-width: 1080px) {
    .hizmet-izgara, .blog-izgara { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .galeri-izgara { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .yorum-kart { flex-basis: calc((100% - 24px) / 2); }
    .alt-izgara { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .hakkinda { gap: 60px; }
    .hk-dekor { left: -16px; bottom: -16px; }
}
@media (max-width: 900px) {
    .bolum { padding: 80px 0; }
    .bolum-bas { margin-bottom: 42px; }
    .hero { min-height: 0; padding: 104px 0 56px; }
    .hero-slayt { grid-template-columns: minmax(0, 1fr); gap: 36px; text-align: center; }
    .hero-gorsel { order: -1; justify-self: center; width: min(76vw, 360px); margin: 0; }
    .hero-cerceve { aspect-ratio: 1 / 1.08; }
    .hero-halka { inset: -14px -16px 16px 16px; }
    .hero-daire { width: 84px; height: 84px; left: -26px; bottom: 60px; }
    .hero-metin { margin-inline: auto; }
    .hero-rozet { margin-bottom: 18px; }
    .hero-aciklama { margin-inline: auto; font-size: 1.06rem; }
    .hero-butonlar { justify-content: center; margin-top: 28px; }
    .hero-kart { padding: 9px 14px 9px 9px; border-radius: 16px; }
    .hero-kart .hk-i { width: 36px; height: 36px; flex-basis: 36px; font-size: 15px; border-radius: 11px; }
    .hero-kart strong { font-size: 1.12rem; }
    .hero-kart small { font-size: 11.5px; }
    .hero-kart.k1 { left: -30px; bottom: 26px; }
    .hero-kart.k2 { right: -26px; top: 34px; }
    .hero-kontrol { justify-content: center; margin-top: 38px; }
    .hakkinda { grid-template-columns: minmax(0, 1fr); gap: 64px; }
    .hk-gorsel { max-width: 400px; }
    .sss { grid-template-columns: minmax(0, 1fr); gap: 0; }
    .sss-sol { display: contents; } /* mobilde destek kartı soruların altına iner */
    .sss .bolum-bas.sol { order: 0; text-align: center; margin: 0 auto 42px; }
    .sss .ust-baslik::after { display: block; }
    .sss-liste { order: 1; }
    .sss-destek { order: 2; margin-top: 26px; }
    .vki { grid-template-columns: minmax(0, 1fr); gap: 0; }
    .vki-sol { display: contents; } /* mobilde sıra: başlık, hesaplayıcı, sınıflandırma */
    .vki .bolum-bas.sol { order: 0; text-align: center; margin: 0 auto 36px; }
    .vki .ust-baslik::after { display: block; }
    .vki-kart { order: 1; }
    .vki-aralik { order: 2; margin-top: 22px; }
    .iletisim { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 760px) {
    .ist-izgara { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 40px; }
    .ist-oge:nth-child(2n+1)::before { display: none; }
    .istatistik { padding: 76px 0; }
    .ist-baslik { margin-bottom: 44px; }
}
@media (max-width: 640px) {
    .kap { width: calc(100% - 32px); }
    .bolum { padding: 68px 0; }
    .logo-yazi { font-size: 1.25rem; }
    .logo-ikon { width: 40px; height: 40px; flex-basis: 40px; font-size: 16px; }
    .hizmet-izgara { grid-template-columns: minmax(0, 1fr); gap: 14px; }
    .hizmet-kart { display: grid; grid-template-columns: 58px minmax(0, 1fr); column-gap: 18px; padding: 22px 22px 24px; border-radius: 22px; }
    .hizmet-ikon { grid-row: span 2; width: 58px; height: 58px; margin: 2px 0 0; font-size: 22px; border-radius: 18px 6px 18px 6px; }
    .hizmet-kart h3 { font-size: 1.22rem; margin-bottom: 4px; }
    .hizmet-kart p { font-size: .95rem; }
    .hizmet-no { display: none; }
    .galeri-izgara { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .galeri-oge { border-radius: 16px; }
    .go-oynat { width: 44px; height: 44px; margin: -22px 0 0 -22px; font-size: 14px; }
    .go-oynat::before { inset: -6px; }
    .go-rozet, .go-baslik { display: none; }
    .galeri-filtre { flex-wrap: nowrap; justify-content: flex-start; overflow-x: auto; margin: -14px -16px 24px; padding: 0 16px 4px; scrollbar-width: none; gap: 8px; }
    .galeri-filtre::-webkit-scrollbar { display: none; }
    .galeri-filtre button { flex-shrink: 0; padding: 9px 15px; font-size: 13.5px; }
    .galeri-filtre button:first-child { margin-left: auto; }
    .galeri-filtre button:last-child { margin-right: auto; }
    .isik-icerik { padding: 0 12px; }
    .isik-medya img { max-height: 70vh; border-radius: 12px; }
    .isik-video { width: calc(100vw - 24px); border-radius: 12px; }
    .isik-ok { top: auto; bottom: calc(24px + env(safe-area-inset-bottom)); margin-top: 0; }
    .isik-ok.onceki { left: calc(50% - 64px); }
    .isik-ok.sonraki { right: calc(50% - 64px); }
    .hk-gorsel { width: calc(100% - 28px); }
    .hk-cerceve { aspect-ratio: 1 / 1.1; border-radius: 28px; }
    .hk-rozet { right: -14px; bottom: 26px; padding: 14px 16px; border-radius: 18px; gap: 10px; }
    .hk-rozet strong { font-size: 2rem; }
    .hk-rozet span { font-size: 12.5px; max-width: 70px; }
    .hk-dekor { left: -14px; bottom: -14px; }
    .hk-liste { grid-template-columns: minmax(0, 1fr); }
    .blog-izgara { grid-template-columns: minmax(0, 1fr); gap: 18px; }
    .blog-gorsel { aspect-ratio: 16 / 8; }
    .bg-ikon { width: 76px; height: 76px; font-size: 30px; border-radius: 26px 8px 26px 8px; }
    .blog-govde { padding: 22px 22px 26px; }
    .modal { padding: 0; align-items: flex-end; }
    .modal-kutu { max-height: 92vh; max-height: 92dvh; border-radius: 26px 26px 0 0; }
    .modal-icerik { padding: 40px 22px calc(30px + env(safe-area-inset-bottom)); }
    .modal-kapat { top: 12px; right: 12px; width: 42px; height: 42px; }
    .yazi-cta { flex-direction: column; align-items: stretch; text-align: center; }
    .yorum-kart { flex-basis: 86%; padding: 26px 22px 22px; border-radius: 24px; }
    .yorum-ray { gap: 14px; }
    .sss-soru { padding: 18px 18px 18px 20px; font-size: 1rem; }
    .sss-cevap p { padding: 0 20px 20px; }
    .sss-destek { padding: 26px 22px; }
    .kart, .il-bilgi .kart { padding: 26px 20px; border-radius: 24px; }
    .alan-ikili { grid-template-columns: minmax(0, 1fr); gap: 0; }
    .vki-kart { padding: 26px 20px; }
    .vki-sonuc { padding: 20px 18px 16px; margin-top: 26px; }
    .vki-sayi { font-size: 2.6rem; }
    .vki-aralik li { padding: 11px 15px; font-size: .93rem; }
    .vki-aralik li.aktif { transform: none; }
    .il-harita { border-radius: 20px; aspect-ratio: 4 / 3; }
    .alt { padding-top: 64px; }
    .alt-izgara { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 40px 24px; }
    .alt-marka, .alt-iletisim-kol { grid-column: 1 / -1; }
    .alt-son { flex-direction: column-reverse; text-align: center; margin-top: 50px; padding-bottom: calc(90px + env(safe-area-inset-bottom)); }
    .cerez { left: 10px; right: 10px; width: auto; bottom: calc(10px + env(safe-area-inset-bottom)); padding: 18px 18px 16px; border-radius: 22px; }
    .cerez-acik .wa-sabit, .cerez-acik .ara-sabit { opacity: 0; transform: translateY(24px) scale(.85); pointer-events: none; }
    .ara-var .alt-son { padding-bottom: calc(160px + env(safe-area-inset-bottom)); }
    .wa-sabit { width: 56px; height: 56px; font-size: 28px; right: 16px; bottom: calc(16px + env(safe-area-inset-bottom)); }
    .ara-sabit { width: 56px; height: 56px; right: 16px; bottom: calc(16px + env(safe-area-inset-bottom)); font-size: 21px; }
    .wa-sabit ~ .ara-sabit { bottom: calc(84px + env(safe-area-inset-bottom)); }
}
@media (max-width: 480px) {
    .hero-butonlar .btn { flex: 1 1 100%; }
    .hero-nokta .cubuk { width: 30px; }
    .hero-noktalar { gap: 12px; }
    .hero-kontrol { gap: 14px; }
    .hero-ok { width: 44px; height: 44px; flex-basis: 44px; }
    .hk-butonlar .btn { flex: 1 1 100%; }
}
@media (hover: none) {
    .go-baslik { opacity: 1; transform: none; font-size: 13px; }
    .galeri-oge::after { opacity: .75; }
    .go-buyut { display: none; }
}
@media (min-width: 901px) and (max-height: 820px) {
    .hero-gorsel { width: min(100%, 390px); }
    .hero-kontrol { margin-top: 36px; }
}
@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
    *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; transition-delay: 0s !important; }
    .hero-nokta.aktif .dolum { animation: none; transform: scaleX(1); }
}
</style>
</head>
<body<?= $aramaButonu ? ' class="ara-var"' : '' ?>>

<?php if ($veriYok): ?>
<div class="veri-uyari">veriler.txt dosyası bulunamadı veya okunamadı. Dosyayı index.php ile aynı klasöre yükleyin ya da admin.php üzerinden kaydedin.</div>
<?php endif; ?>

<!-- ================= ÜST MENÜ ================= -->
<header class="ust" id="ust">
    <div class="kap ust-ic">
        <a class="logo" href="#anasayfa" aria-label="<?= e($siteAdi) ?> - Ana Sayfa">
            <span class="logo-ikon"><i class="<?= e($logoIkon) ?>"></i></span>
            <span class="logo-yazi"><?= e($siteAdi) ?></span>
        </a>
        <nav class="menu" aria-label="Ana menü">
            <?php foreach ($menu as $k => $m): if (!$m['goster']) continue; ?>
                <?php if ($k === 'whatsapp'): ?>
                    <a class="menu-wa" href="<?= e($m['link']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i><?= e($m['yazi']) ?></a>
                <?php else: ?>
                    <a href="<?= e($m['link']) ?>"><?= e($m['yazi']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php if ($aramaButonu): ?>
        <a class="ust-ara" href="<?= e(tel_link($telefon)) ?>" aria-label="Hemen ara: <?= e($telefon) ?>"><i class="fa-solid fa-phone"></i></a>
        <?php endif; ?>
        <?php if ($menuDolu): ?>
        <button class="menu-ac" type="button" aria-label="Menüyü aç" aria-expanded="false" aria-controls="cekmece">
            <span></span><span></span><span></span>
        </button>
        <?php endif; ?>
    </div>
</header>

<div class="cekmece" id="cekmece" aria-hidden="true">
    <div class="cekmece-arka" data-kapat></div>
    <div class="cekmece-panel" role="dialog" aria-modal="true" aria-label="Menü">
        <div class="cekmece-ust">
            <a class="logo" href="#anasayfa" data-kapat>
                <span class="logo-ikon"><i class="<?= e($logoIkon) ?>"></i></span>
                <span class="logo-yazi"><?= e($siteAdi) ?></span>
            </a>
            <button class="cekmece-kapat" type="button" aria-label="Menüyü kapat" data-kapat><i class="fa-solid fa-xmark"></i></button>
        </div>
        <nav aria-label="Mobil menü">
            <?php $no = 0; foreach ($menu as $k => $m): if (!$m['goster'] || $k === 'whatsapp') continue; $no++; ?>
                <a href="<?= e($m['link']) ?>" data-kapat style="transition-delay: <?= 80 + $no * 40 ?>ms"><span class="cn"><?= sprintf('%02d', $no) ?></span><?= e($m['yazi']) ?><i class="fa-solid fa-arrow-right"></i></a>
            <?php endforeach; ?>
        </nav>
        <div class="cekmece-alt">
            <?php if ($menu['whatsapp']['goster']): ?>
                <a class="btn btn-ana" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i><?= e($menu['whatsapp']['yazi']) ?></a>
            <?php endif; ?>
            <?php if (preg_match('/\d/', $telefon)): ?>
                <a class="btn btn-cizgi" href="<?= e(tel_link($telefon)) ?>" aria-label="Hemen ara: <?= e($telefon) ?>"><i class="fa-solid fa-phone"></i><?= e($telefon) ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<main>

<!-- ================= HERO (KARŞILAMA) ================= -->
<section class="hero" id="anasayfa">
    <div class="hero-zemin" aria-hidden="true">
        <span class="leke l1"></span><span class="leke l2"></span><span class="noktalar"></span>
    </div>
    <div class="kap">
        <?php if (empty($slaytlar)): ?>
            <div class="hero-slaytlar">
                <div class="hero-slayt aktif">
                    <div class="hero-metin">
                        <h1 class="hero-baslik"><?= baslik_vurgula($siteAdi, true) ?></h1>
                        <?php if ($siteAciklama !== ''): ?><p class="hero-aciklama"><?= e($siteAciklama) ?></p><?php endif; ?>
                        <?php if ($waLink !== ''): ?>
                            <div class="hero-butonlar"><a class="btn btn-ana" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="hero-slaytlar" aria-roledescription="carousel" aria-label="Öne çıkanlar">
                <?php foreach ($slaytlar as $i => $s): $etiket = $i === 0 ? 'h1' : 'h2'; $butonLink = link_temizle($s['buton_link']); ?>
                <div class="hero-slayt<?= $i === 0 ? ' aktif' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= ($i + 1) . ' / ' . count($slaytlar) ?>"<?= $i === 0 ? '' : ' inert' ?>>
                    <div class="hero-metin">
                        <?php if ($s['ust_baslik'] !== ''): ?>
                            <span class="hero-rozet"><i class="<?= e($logoIkon) ?>"></i><?= e($s['ust_baslik']) ?></span>
                        <?php endif; ?>
                        <<?= $etiket ?> class="hero-baslik"><?= baslik_vurgula($s['baslik'], true) ?></<?= $etiket ?>>
                        <?php if ($s['aciklama'] !== ''): ?>
                            <p class="hero-aciklama"><?= nl2br(e($s['aciklama']), false) ?></p>
                        <?php endif; ?>
                        <?php if (($s['buton_yazi'] !== '' && $butonLink !== '') || $waLink !== ''): ?>
                        <div class="hero-butonlar">
                            <?php if ($s['buton_yazi'] !== '' && $butonLink !== ''): ?>
                                <a class="btn btn-ana" href="<?= e($butonLink) ?>"<?= preg_match('~^https?://~i', $butonLink) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($s['buton_yazi']) ?><i class="fa-solid fa-arrow-right"></i></a>
                            <?php endif; ?>
                            <?php if ($waLink !== ''): ?>
                                <a class="btn <?= $s['buton_yazi'] !== '' && $butonLink !== '' ? 'btn-cizgi' : 'btn-ana' ?>" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="hero-gorsel">
                        <span class="hero-halka" aria-hidden="true"></span>
                        <span class="hero-daire" aria-hidden="true"></span>
                        <div class="hero-cerceve">
                            <?php if ($s['gorsel'] !== ''): ?>
                                <?php
                                $heroResim = resim_oznitelik($s['gorsel'], array(480, 800, 1100), $HERO_BOYUT);
                                if ($i > 0) { // diğer slaytların fotoğrafları sayfa yüklendikten sonra iner
                                    $heroResim = str_replace(array(' srcset="', 'src="'), array(' data-srcset="', 'data-src="'), $heroResim);
                                }
                                ?>
                                <img <?= $heroResim ?> alt="<?= e(yildizsiz($s['baslik'])) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' decoding="async"' ?>>
                            <?php endif; ?>
                        </div>
                        <?php foreach ($heroKartlar as $no => $kart): ?>
                            <div class="hero-kart k<?= $no ?>" aria-hidden="true">
                                <div class="yuz">
                                    <span class="hk-i"><i class="fa-solid <?= $no === 1 ? 'fa-face-smile' : 'fa-star' ?>"></i></span>
                                    <span><strong><?= e($kart['sayi']) ?></strong><small><?= e($kart['yazi']) ?></small></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($slaytlar) > 1): ?>
            <div class="hero-kontrol">
                <button class="hero-ok onceki" type="button" aria-label="Önceki slayt"><i class="fa-solid fa-arrow-left"></i></button>
                <div class="hero-noktalar">
                    <?php foreach ($slaytlar as $i => $s): ?>
                        <button class="hero-nokta<?= $i === 0 ? ' aktif' : '' ?>" type="button" aria-label="<?= ($i + 1) ?>. slayt"><?= sprintf('%02d', $i + 1) ?><span class="cubuk"><span class="dolum"></span></span></button>
                    <?php endforeach; ?>
                </div>
                <button class="hero-ok sonraki" type="button" aria-label="Sonraki slayt"><i class="fa-solid fa-arrow-right"></i></button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if (bolum_acik('hizmetler')): ?>
<!-- ================= HİZMETLER ================= -->
<section class="bolum" id="hizmetler">
    <div class="kap">
        <div class="bolum-bas reveal">
            <?php if (al($V, 'hizmetler.ust_baslik') !== ''): ?><span class="ust-baslik"><?= e(al($V, 'hizmetler.ust_baslik')) ?></span><?php endif; ?>
            <h2><?= baslik_vurgula(al($V, 'hizmetler.baslik', $menu['hizmetler']['yazi'])) ?></h2>
            <?php if (al($V, 'hizmetler.aciklama') !== ''): ?><p><?= e(al($V, 'hizmetler.aciklama')) ?></p><?php endif; ?>
        </div>
        <div class="hizmet-izgara">
            <?php foreach ($hizmetler as $i => $h): ?>
                <article class="hizmet-kart reveal" style="--s: <?= $i % 3 ?>">
                    <span class="hizmet-no" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
                    <div class="hizmet-ikon"><i class="<?= e(ikon($h['ikon'])) ?>"></i></div>
                    <h3><?= e($h['baslik']) ?></h3>
                    <p><?= e($h['aciklama']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($vkiGoster): ?>
<!-- ================= VKİ HESAPLAMA ================= -->
<section class="bolum bolum-alt" id="vki">
    <div class="kap vki">
        <div class="vki-sol reveal">
            <div class="bolum-bas sol">
                <?php if (al($V, 'vki.ust_baslik', 'VKİ Hesaplama') !== ''): ?><span class="ust-baslik"><?= e(al($V, 'vki.ust_baslik', 'VKİ Hesaplama')) ?></span><?php endif; ?>
                <h2><?= baslik_vurgula(al($V, 'vki.baslik', 'Vücut Kitle *İndeksinizi* Hesaplayın')) ?></h2>
                <?php if (al($V, 'vki.aciklama') !== ''): ?><p><?= e(al($V, 'vki.aciklama')) ?></p><?php endif; ?>
            </div>
            <ul class="vki-aralik" aria-label="VKİ sınıflandırması">
                <li style="--r: #4ea3e0"><span>Zayıf</span><b>18,5 altı</b></li>
                <li style="--r: #2f9e5b"><span>Normal</span><b>18,5 – 24,9</b></li>
                <li style="--r: #e0a020"><span>Fazla kilolu</span><b>25 – 29,9</b></li>
                <li style="--r: #ef7d3c"><span>Obez</span><b>30 – 39,9</b></li>
                <li style="--r: #d9443a"><span>İleri derece obez</span><b>40 ve üzeri</b></li>
            </ul>
        </div>
        <form class="kart vki-kart reveal" id="vkiFormu" style="--s: 1" novalidate>
            <div class="vki-alan">
                <div class="vki-alan-ust">
                    <label for="vkiBoy">Boyunuz</label>
                    <span class="vki-giris"><input type="number" id="vkiBoy" inputmode="numeric" min="100" max="230" step="1" value="165"><span>cm</span></span>
                </div>
                <input type="range" class="vki-kaydirac" min="100" max="230" step="1" value="165" data-bagli="vkiBoy" aria-label="Boy (cm)">
            </div>
            <div class="vki-alan">
                <div class="vki-alan-ust">
                    <label for="vkiKilo">Kilonuz</label>
                    <span class="vki-giris"><input type="number" id="vkiKilo" inputmode="decimal" min="30" max="250" step="0.1" value="65"><span>kg</span></span>
                </div>
                <input type="range" class="vki-kaydirac" min="30" max="200" step="1" value="65" data-bagli="vkiKilo" aria-label="Kilo (kg)">
            </div>
            <div class="vki-sonuc" aria-live="polite">
                <div class="vki-sonuc-ust">
                    <div><small>Vücut Kitle İndeksiniz</small><strong class="vki-sayi">–</strong></div>
                    <span class="vki-durum">–</span>
                </div>
                <div class="vki-cubuk" aria-hidden="true"><span class="vki-isaret"></span></div>
                <div class="vki-olcek" aria-hidden="true"><span style="left: 11.67%">18,5</span><span style="left: 33.33%">25</span><span style="left: 50%">30</span><span style="left: 83.33%">40</span></div>
                <p class="vki-yorum"></p>
            </div>
            <?php if ($waLink !== ''): ?>
                <a class="btn btn-ana vki-btn" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i><?= e(al($V, 'vki.buton_yazi', 'Sonucumu Diyetisyene Gönder')) ?></a>
            <?php else: ?>
                <a class="btn btn-ana vki-btn" href="#iletisim"><?= e(al($V, 'vki.buton_yazi', 'Sonucumu Diyetisyene Gönder')) ?><i class="fa-solid fa-arrow-right"></i></a>
            <?php endif; ?>
            <?php if ($vkiUyari !== ''): ?>
                <p class="vki-not"><i class="fa-solid fa-circle-info"></i><span><?= e($vkiUyari) ?></span></p>
            <?php endif; ?>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($istGoster): ?>
<!-- ================= İSTATİSTİKLER ================= -->
<section class="istatistik" aria-label="<?= e(yildizsiz(al($V, 'istatistik.baslik', 'İstatistikler'))) ?>">
    <div class="kap">
        <?php if (al($V, 'istatistik.baslik') !== ''): ?>
            <h2 class="ist-baslik reveal"><?= baslik_vurgula(al($V, 'istatistik.baslik')) ?></h2>
        <?php endif; ?>
        <div class="ist-izgara">
            <?php foreach ($istatistik as $i => $s): $tamSayi = preg_match('/^\d+$/', $s['sayi']); ?>
                <div class="ist-oge reveal" style="--s: <?= $i ?>">
                    <div class="ist-sayi">
                        <?php if ($s['on_ek'] !== ''): ?><span class="ek"><?= e($s['on_ek']) ?></span><?php endif; ?><span<?= $tamSayi ? ' data-sayac="' . (int) $s['sayi'] . '"' : '' ?>><?= $tamSayi ? number_format((float) $s['sayi'], 0, ',', '.') : e($s['sayi']) ?></span><?php if ($s['son_ek'] !== ''): ?><span class="ek"><?= e($s['son_ek']) ?></span><?php endif; ?>
                    </div>
                    <div class="ist-etiket"><?= e($s['etiket']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (bolum_acik('galeri')): ?>
<!-- ================= GALERİ ================= -->
<section class="bolum" id="galeri">
    <div class="kap">
        <div class="bolum-bas reveal">
            <?php if (al($V, 'galeri.ust_baslik') !== ''): ?><span class="ust-baslik"><?= e(al($V, 'galeri.ust_baslik')) ?></span><?php endif; ?>
            <h2><?= baslik_vurgula(al($V, 'galeri.baslik', $menu['galeri']['yazi'])) ?></h2>
            <?php if (al($V, 'galeri.aciklama') !== ''): ?><p><?= e(al($V, 'galeri.aciklama')) ?></p><?php endif; ?>
        </div>
        <?php if ($galeriResim > 0 && $galeriVideo > 0): ?>
            <div class="galeri-filtre reveal" role="group" aria-label="Galeri filtresi">
                <button type="button" class="aktif" data-filtre="hepsi" aria-pressed="true">Tümü <span class="say"><?= count($galeri) ?></span></button>
                <button type="button" data-filtre="resim" aria-pressed="false"><i class="fa-regular fa-image"></i>Fotoğraflar <span class="say"><?= $galeriResim ?></span></button>
                <button type="button" data-filtre="video" aria-pressed="false"><i class="fa-solid fa-circle-play"></i>Videolar <span class="say"><?= $galeriVideo ?></span></button>
            </div>
        <?php endif; ?>
        <div class="galeri-izgara">
            <?php foreach ($galeri as $i => $g): $altYazi = $g['baslik'] !== '' ? $g['baslik'] : ($g['tur'] === 'video' ? 'Video' : 'Galeri görseli') . ' ' . ($i + 1); ?>
                <a class="galeri-oge reveal<?= $g['tur'] === 'video' ? ' video' : '' ?>" style="--s: <?= $i % 4 ?>"
                   href="<?= e($g['link']) ?>" target="_blank" rel="noopener"
                   data-tur="<?= $g['tur'] ?>" data-kaynak="<?= e($g['kaynak']) ?>" data-buyuk="<?= e($g['buyuk']) ?>" data-baslik="<?= e($g['baslik']) ?>"
                   aria-label="<?= e($altYazi) ?><?= $g['tur'] === 'video' ? ' (videoyu oynat)' : ' (büyüt)' ?>">
                    <?php if ($g['tur'] === 'video'): ?>
                        <img src="<?= e($g['kucuk']) ?>" alt="<?= e($altYazi) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <img <?= resim_oznitelik($g['kaynak'], array(400, 700), '(max-width: 640px) 50vw, (max-width: 1080px) 33vw, 300px') ?> alt="<?= e($altYazi) ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                    <?php if ($g['tur'] === 'video'): ?>
                        <span class="go-rozet"><i class="fa-brands fa-youtube"></i>Video</span>
                        <span class="go-oynat"><i class="fa-solid fa-play"></i></span>
                    <?php else: ?>
                        <span class="go-buyut"><i class="fa-solid fa-expand"></i></span>
                    <?php endif; ?>
                    <?php if ($g['baslik'] !== ''): ?><span class="go-baslik"><?= e($g['baslik']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (bolum_acik('hakkinda')): $hkGorsel = link_temizle(al($V, 'hakkinda.gorsel')); ?>
<!-- ================= HAKKINDA ================= -->
<section class="bolum bolum-alt" id="hakkinda">
    <div class="kap hakkinda">
        <div class="hk-gorsel reveal">
            <span class="hk-dekor" aria-hidden="true"></span>
            <div class="hk-cerceve">
                <?php if ($hkGorsel !== ''): ?>
                    <img <?= resim_oznitelik($hkGorsel, array(500, 900), '(max-width: 900px) 90vw, 450px') ?> alt="<?= e(al($V, 'hakkinda.isim')) ?>" loading="lazy" decoding="async">
                <?php endif; ?>
            </div>
            <?php if (al($V, 'hakkinda.rozet_sayi') !== ''): ?>
                <div class="hk-rozet"><strong><?= e(al($V, 'hakkinda.rozet_sayi')) ?></strong><span><?= e(al($V, 'hakkinda.rozet_yazi')) ?></span></div>
            <?php endif; ?>
        </div>
        <div class="hk-metin reveal" style="--s: 1">
            <span class="ust-baslik sol"><?= e(al($V, 'hakkinda.baslik', $menu['hakkinda']['yazi'])) ?></span>
            <h2><?= baslik_vurgula(al($V, 'hakkinda.isim')) ?></h2>
            <?php if (al($V, 'hakkinda.unvan') !== ''): ?><p class="hk-unvan"><?= e(al($V, 'hakkinda.unvan')) ?></p><?php endif; ?>
            <div class="hk-yazi"><?= metin_bicimle($hkMetin) ?></div>
            <?php if ($hkListe): ?>
                <ul class="hk-liste">
                    <?php foreach ($hkListe as $madde): ?><li><i class="fa-solid fa-check"></i><?= e($madde) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="hk-butonlar"<?= $hkListe ? '' : ' style="margin-top: 34px"' ?>>
                <?php if ($waLink !== ''): ?><a class="btn btn-ana" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Randevu Alın</a><?php endif; ?>
                <a class="btn btn-cizgi" href="#iletisim"><?= e($menu['iletisim']['yazi']) ?><i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (bolum_acik('blog')): ?>
<!-- ================= BLOG ================= -->
<section class="bolum" id="blog">
    <div class="kap">
        <div class="bolum-bas reveal">
            <?php if (al($V, 'blog.ust_baslik') !== ''): ?><span class="ust-baslik"><?= e(al($V, 'blog.ust_baslik')) ?></span><?php endif; ?>
            <h2><?= baslik_vurgula(al($V, 'blog.baslik', $menu['blog']['yazi'])) ?></h2>
            <?php if (al($V, 'blog.aciklama') !== ''): ?><p><?= e(al($V, 'blog.aciklama')) ?></p><?php endif; ?>
        </div>
        <div class="blog-izgara">
            <?php foreach ($blog as $i => $b): $bGorsel = link_temizle($b['gorsel']); ?>
                <article class="blog-kart reveal" style="--s: <?= $i % 3 ?>">
                    <a class="blog-gorsel<?= $bGorsel === '' ? ' ikonlu' : '' ?>" href="#yazi-<?= $i + 1 ?>" data-yazi="<?= $i ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($bGorsel !== ''): ?>
                            <img <?= resim_oznitelik($bGorsel, array(500, 900), '(max-width: 640px) 92vw, (max-width: 1080px) 46vw, 380px') ?> alt="" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="bg-ikon"><i class="<?= e(ikon($b['ikon'], 'fa-solid fa-apple-whole')) ?>"></i></span>
                        <?php endif; ?>
                        <?php if ($b['tarih'] !== ''): ?><span class="blog-tarih"><?= e($b['tarih']) ?></span><?php endif; ?>
                    </a>
                    <div class="blog-govde">
                        <h3><a href="#yazi-<?= $i + 1 ?>" data-yazi="<?= $i ?>"><?= e($b['baslik']) ?></a></h3>
                        <p><?= e($b['ozet']) ?></p>
                        <a class="blog-devam" href="#yazi-<?= $i + 1 ?>" data-yazi="<?= $i ?>" aria-label="<?= e($b['baslik']) ?> - devamını oku">Devamını Oku <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </article>
                <template id="yazi-sablon-<?= $i ?>">
                    <article class="yazi">
                        <div class="yazi-ust">
                            <span class="yi"><i class="<?= e(ikon($b['ikon'], 'fa-solid fa-apple-whole')) ?>"></i></span>
                            <span><?= e(trim($b['tarih'] . ($b['tarih'] !== '' ? ' · ' : '') . $siteAdi)) ?></span>
                        </div>
                        <h2 id="modalBaslik"><?= e($b['baslik']) ?></h2>
                        <?php if ($bGorsel !== ''): ?><div class="yazi-gorsel"><img <?= resim_oznitelik($bGorsel, array(900, 1400), '(max-width: 820px) 92vw, 680px') ?> alt=""></div><?php endif; ?>
                        <div class="yazi-govde"><?= metin_bicimle($b['icerik'] !== '' ? $b['icerik'] : $b['ozet']) ?></div>
                        <?php if ($waLink !== ''): ?>
                            <div class="yazi-cta">
                                <p>Size özel bir beslenme programı ister misiniz?</p>
                                <a class="btn btn-ana" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Randevu Alın</a>
                            </div>
                        <?php endif; ?>
                    </article>
                </template>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (bolum_acik('yorumlar')): ?>
<!-- ================= YORUMLAR ================= -->
<section class="bolum bolum-alt" id="yorumlar">
    <div class="kap">
        <div class="bolum-bas reveal">
            <?php if (al($V, 'yorumlar.ust_baslik') !== ''): ?><span class="ust-baslik"><?= e(al($V, 'yorumlar.ust_baslik')) ?></span><?php endif; ?>
            <h2><?= baslik_vurgula(al($V, 'yorumlar.baslik', $menu['yorumlar']['yazi'])) ?></h2>
            <?php if (al($V, 'yorumlar.aciklama') !== ''): ?><p><?= e(al($V, 'yorumlar.aciklama')) ?></p><?php endif; ?>
        </div>
        <div class="yorum-kaydirici reveal">
            <div class="yorum-ray" tabindex="0" aria-label="Danışan yorumları (yana kaydırın)">
                <?php foreach ($yorumlar as $y): ?>
                    <figure class="yorum-kart">
                        <div class="yk-ust">
                            <span class="yk-yildiz" aria-label="5 üzerinden 5 yıldız"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
                            <i class="fa-solid fa-quote-right yk-tirnak" aria-hidden="true"></i>
                        </div>
                        <blockquote><?= nl2br(e($y['yorum']), false) ?></blockquote>
                        <figcaption>
                            <span class="yk-avatar" aria-hidden="true"><?= bas_harf($y['isim']) ?></span>
                            <span><strong><?= e($y['isim']) ?></strong><small>Danışanımız</small></span>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php if (count($yorumlar) > 1): ?>
            <div class="yorum-kontrol">
                <button class="hero-ok yorum-onceki" type="button" aria-label="Önceki yorum"><i class="fa-solid fa-arrow-left"></i></button>
                <div class="yorum-noktalar"></div>
                <button class="hero-ok yorum-sonraki" type="button" aria-label="Sonraki yorum"><i class="fa-solid fa-arrow-right"></i></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (bolum_acik('sss')): ?>
<!-- ================= S.S.S. ================= -->
<section class="bolum" id="sss">
    <div class="kap sss">
        <div class="sss-sol reveal">
            <div class="bolum-bas sol">
                <?php if (al($V, 'sss.ust_baslik') !== ''): ?><span class="ust-baslik"><?= e(al($V, 'sss.ust_baslik')) ?></span><?php endif; ?>
                <h2><?= baslik_vurgula(al($V, 'sss.baslik', $menu['sss']['yazi'])) ?></h2>
                <?php if (al($V, 'sss.aciklama') !== ''): ?><p><?= e(al($V, 'sss.aciklama')) ?></p><?php endif; ?>
            </div>
            <?php if ($waLink !== ''): ?>
                <div class="sss-destek">
                    <h3>Sorunuz mu var?</h3>
                    <p>Aklınıza takılan her şeyi WhatsApp üzerinden bize sorabilirsiniz.</p>
                    <a class="btn btn-beyaz" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Hemen Sorun</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="sss-liste">
            <?php foreach ($sss as $i => $s): ?>
                <div class="sss-oge reveal<?= $i === 0 ? ' acik' : '' ?>" style="--s: <?= min($i, 4) ?>">
                    <button class="sss-soru" type="button" id="sss-s-<?= $i ?>" aria-controls="sss-c-<?= $i ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                        <span><?= e($s['soru']) ?></span><span class="sss-isaret" aria-hidden="true"></span>
                    </button>
                    <div class="sss-cevap" id="sss-c-<?= $i ?>" role="region" aria-labelledby="sss-s-<?= $i ?>">
                        <div><p><?= nl2br(e($s['cevap']), false) ?></p></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (bolum_acik('iletisim')): ?>
<!-- ================= İLETİŞİM ================= -->
<section class="bolum bolum-alt" id="iletisim">
    <div class="kap">
        <div class="bolum-bas reveal">
            <?php if (al($V, 'iletisim.ust_baslik') !== ''): ?><span class="ust-baslik"><?= e(al($V, 'iletisim.ust_baslik')) ?></span><?php endif; ?>
            <h2><?= baslik_vurgula(al($V, 'iletisim.baslik', $menu['iletisim']['yazi'])) ?></h2>
            <?php if (al($V, 'iletisim.aciklama') !== ''): ?><p><?= e(al($V, 'iletisim.aciklama')) ?></p><?php endif; ?>
        </div>
        <div class="iletisim">
            <form class="kart il-form reveal" id="iletisimFormu" novalidate>
                <h3><?= e(al($V, 'iletisim.form_baslik', 'Bizimle İletişime Geçin')) ?></h3>
                <p class="il-not"><?= $waNumara !== '' ? 'Formu doldurun, mesajınız WhatsApp üzerinden bize iletilsin.' : 'Formu doldurun, mesajınız e-posta ile bize iletilsin.' ?></p>
                <div class="alan-ikili">
                    <div class="alan">
                        <label for="f-ad">Ad Soyad <span>*</span></label>
                        <input type="text" id="f-ad" name="ad" autocomplete="name" required>
                    </div>
                    <div class="alan">
                        <label for="f-tel">Telefon</label>
                        <input type="tel" id="f-tel" name="telefon" autocomplete="tel" inputmode="tel">
                    </div>
                </div>
                <div class="alan">
                    <label for="f-eposta">E-posta</label>
                    <input type="email" id="f-eposta" name="eposta" autocomplete="email">
                </div>
                <div class="alan">
                    <label for="f-mesaj">Mesajınız <span>*</span></label>
                    <textarea id="f-mesaj" name="mesaj" required></textarea>
                </div>
                <?php if ($kvkkMetni !== ''): ?>
                    <label class="onay-kutu">
                        <input type="checkbox" name="kvkk" value="1">
                        <span class="onay-isaret" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                        <span class="onay-yazi"><?= form_onay_html($formOnay) ?></span>
                    </label>
                <?php endif; ?>
                <button class="btn btn-ana" type="submit"><i class="fa-solid fa-paper-plane"></i>Gönder</button>
                <p class="form-durum" role="status" aria-live="polite"></p>
            </form>

            <div class="il-bilgi reveal" style="--s: 1">
                <div class="kart">
                    <?php if ($adres !== ''): ?>
                        <div class="il-satir"><span class="ik"><i class="fa-solid fa-location-dot"></i></span><div><h4>Adres</h4><p><?= e($adres) ?></p></div></div>
                    <?php endif; ?>
                    <?php if ($telefon !== ''): ?>
                        <div class="il-satir"><span class="ik"><i class="fa-solid fa-phone"></i></span><div><h4>Telefon</h4><a href="<?= e(tel_link($telefon)) ?>"><?= e($telefon) ?></a></div></div>
                    <?php endif; ?>
                    <?php if ($eposta !== ''): ?>
                        <div class="il-satir"><span class="ik"><i class="fa-solid fa-envelope"></i></span><div><h4>E-posta</h4><a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a></div></div>
                    <?php endif; ?>
                    <?php if ($saatler): ?>
                        <div class="il-satir"><span class="ik"><i class="fa-solid fa-clock"></i></span><div><h4>Çalışma Saatleri</h4><p><?= implode('<br>', array_map('e', $saatler)) ?></p></div></div>
                    <?php endif; ?>
                    <?php if ($haritaLink !== ''): ?>
                        <a class="btn btn-ana" href="<?= e($haritaLink) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-map-location-dot"></i>Google Haritalarda Aç</a>
                    <?php endif; ?>
                </div>
                <?php if ($haritaGoster): ?>
                    <div class="il-harita">
                        <?php /* Harita, çerez onayı verilene kadar yüklenmez */ ?>
                        <iframe data-src="https://maps.google.com/maps?q=<?= e(rawurlencode($adres)) ?>&amp;z=15&amp;output=embed" title="Konum haritası" loading="lazy" referrerpolicy="no-referrer-when-downgrade" hidden></iframe>
                        <div class="harita-onay">
                            <span class="ho-ikon"><i class="fa-solid fa-map-location-dot"></i></span>
                            <p>Harita, Google Haritalar tarafından gösterilir ve çerez kullanabilir.</p>
                            <button type="button" class="btn btn-ana" data-harita-yukle>Haritayı Göster</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

</main>

<!-- ================= ALT BİLGİ ================= -->
<footer class="alt">
    <div class="kap">
        <div class="alt-izgara">
            <div class="alt-marka">
                <a class="logo" href="#anasayfa">
                    <span class="logo-ikon"><i class="<?= e($logoIkon) ?>"></i></span>
                    <span class="logo-yazi"><?= e($siteAdi) ?></span>
                </a>
                <?php if (al($V, 'footer.aciklama') !== ''): ?><p><?= e(al($V, 'footer.aciklama')) ?></p><?php endif; ?>
                <?php if ($sosyal): ?>
                    <div class="sosyal">
                        <?php foreach ($sosyal as $ag => $s): ?>
                            <a href="<?= e($s['link']) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($ag)) ?>"><i class="fa-brands <?= $s['ikon'] ?>"></i></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($menuDolu): ?>
            <div>
                <h4>Hızlı Linkler</h4>
                <ul>
                    <?php foreach ($menu as $k => $m): if (!$m['goster']) continue; ?>
                        <li><a href="<?= e($m['link']) ?>"<?= $k === 'whatsapp' ? ' target="_blank" rel="noopener"' : '' ?>><?= e($m['yazi']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (bolum_acik('blog')): ?>
            <div>
                <h4><?= e($menu['blog']['yazi']) ?></h4>
                <ul class="alt-blog">
                    <?php foreach (array_slice($blog, 0, 4) as $i => $b): ?>
                        <li><a href="#yazi-<?= $i + 1 ?>" data-yazi="<?= $i ?>"><i class="fa-solid fa-chevron-right"></i><?= e($b['baslik']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($iletisimDolu): ?>
            <div class="alt-iletisim-kol">
                <h4><?= e($menu['iletisim']['yazi']) ?></h4>
                <ul class="alt-iletisim">
                    <?php if ($adres !== ''): ?><li><i class="fa-solid fa-location-dot"></i><span><?= e($adres) ?></span></li><?php endif; ?>
                    <?php if ($telefon !== ''): ?><li><i class="fa-solid fa-phone"></i><a href="<?= e(tel_link($telefon)) ?>"><?= e($telefon) ?></a></li><?php endif; ?>
                    <?php if ($eposta !== ''): ?><li><i class="fa-solid fa-envelope"></i><a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a></li><?php endif; ?>
                    <?php foreach ($saatler as $saat): ?><li><i class="fa-regular fa-clock"></i><span><?= e($saat) ?></span></li><?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <div class="alt-son">
            <p><?= e(al($V, 'footer.telif', '© ' . date('Y') . ' ' . $siteAdi)) ?></p>
            <?php if ($kvkkMetni !== '' || $cerezMetni !== '' || $cerezBandi): ?>
                <nav class="alt-yasal" aria-label="Yasal bilgiler">
                    <?php if ($kvkkMetni !== ''): ?><a href="#kvkk-aydinlatma-metni" data-metin="kvkk">KVKK Aydınlatma Metni</a><?php endif; ?>
                    <?php if ($cerezMetni !== ''): ?><a href="#cerez-politikasi" data-metin="cerez">Çerez Politikası</a><?php endif; ?>
                    <?php if ($cerezBandi): ?><button type="button" data-cerez-ayar>Çerez Tercihleri</button><?php endif; ?>
                </nav>
            <?php endif; ?>
            <a class="alt-yukari" href="#anasayfa">Yukarı Çık <i class="fa-solid fa-arrow-up"></i></a>
        </div>
    </div>
</footer>

<?php if ($waLink !== ''): ?>
<a class="wa-sabit" href="<?= e($waLink) ?>" target="_blank" rel="noopener" aria-label="WhatsApp ile yazın">
    <i class="fa-brands fa-whatsapp"></i><span class="wa-ipucu">Bize WhatsApp'tan yazın</span>
</a>
<?php endif; ?>
<?php if ($aramaButonu): ?>
<a class="ara-sabit" href="<?= e(tel_link($telefon)) ?>" aria-label="Hemen ara: <?= e($telefon) ?>">
    <i class="fa-solid fa-phone"></i>
</a>
<?php endif; ?>

<!-- Galeri büyütme penceresi -->
<div class="isik" id="isik" role="dialog" aria-modal="true" aria-label="Galeri" hidden>
    <div class="isik-ust">
        <span class="isik-sayac"></span>
        <button class="isik-btn isik-kapat" type="button" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <button class="isik-btn isik-ok onceki" type="button" aria-label="Önceki"><i class="fa-solid fa-arrow-left"></i></button>
    <button class="isik-btn isik-ok sonraki" type="button" aria-label="Sonraki"><i class="fa-solid fa-arrow-right"></i></button>
    <figure class="isik-icerik">
        <div class="isik-medya"></div>
        <figcaption class="isik-baslik"></figcaption>
    </figure>
</div>

<!-- Blog yazısı penceresi -->
<div class="modal" id="yaziModal" role="dialog" aria-modal="true" aria-labelledby="modalBaslik" hidden>
    <div class="modal-arka" data-kapat></div>
    <div class="modal-kutu">
        <button class="modal-kapat" type="button" aria-label="Kapat" data-kapat><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-icerik"></div>
    </div>
</div>

<?php if ($kvkkMetni !== ''): ?>
<template id="sablon-kvkk">
    <article class="yazi yasal">
        <div class="yazi-ust"><span class="yi"><i class="fa-solid fa-shield-halved"></i></span><span><?= e($siteAdi) ?></span></div>
        <h2 id="modalBaslik">KVKK Aydınlatma Metni</h2>
        <div class="yazi-govde"><?= yasal_metin($kvkkMetni) ?></div>
    </article>
</template>
<?php endif; ?>
<?php if ($cerezMetni !== ''): ?>
<template id="sablon-cerez">
    <article class="yazi yasal">
        <div class="yazi-ust"><span class="yi"><i class="fa-solid fa-cookie-bite"></i></span><span><?= e($siteAdi) ?></span></div>
        <h2 id="modalBaslik">Çerez Politikası</h2>
        <div class="yazi-govde"><?= yasal_metin($cerezMetni) ?></div>
        <?php if ($cerezBandi): ?>
            <div class="yazi-cta"><p>Tercihinizi istediğiniz zaman değiştirebilirsiniz.</p><button type="button" class="btn btn-ana" data-cerez-ayar><i class="fa-solid fa-sliders"></i>Çerez Tercihleri</button></div>
        <?php endif; ?>
    </article>
</template>
<?php endif; ?>

<?php if ($cerezBandi): ?>
<!-- Çerez bandı: tercih yapılana kadar görünür (JS ile açılır) -->
<div class="cerez" id="cerezBandi" role="region" aria-label="Çerez tercihleri" hidden>
    <div class="cerez-ust">
        <span class="cerez-ikon"><i class="fa-solid fa-cookie-bite"></i></span>
        <h3><?= e($bantBaslik) ?></h3>
    </div>
    <p><?= e($bantMetni) ?><?php if ($cerezMetni !== ''): ?> <a href="#cerez-politikasi" data-metin="cerez">Çerez Politikası</a><?php endif; ?></p>
    <div class="cerez-butonlar">
        <button type="button" class="btn btn-ana" data-cerez="red">Reddet</button>
        <button type="button" class="btn btn-ana" data-cerez="kabul">Kabul Et</button>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    'use strict';
    var AYAR = <?= $jsAyar ?>;
    var d = document, w = window;
    var azHareket = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function hepsi(secici, kok) { return Array.prototype.slice.call((kok || d).querySelectorAll(secici)); }

    /* ---------- Çerez onayı ----------
     * Tercih tarayıcıda 12 ay saklanır. Onay verilmeden Google Haritalar ve YouTube yüklenmez.
     * Admin panelinden çerez bandı kapatılırsa her şey onay sorulmadan yüklenir. */
    var CEREZ_ANAHTAR = 'cerez_tercihi', cerezBellek = null;
    var cerezBant = d.getElementById('cerezBandi');
    function cerezSecimi() {
        try {
            var k = JSON.parse(w.localStorage.getItem(CEREZ_ANAHTAR) || 'null');
            if (k && (k.secim === 'kabul' || k.secim === 'red') && Date.now() - k.zaman < 365 * 864e5) return k.secim;
        } catch (e) { /* gizli sekme vb.: bellekteki seçim kullanılır */ }
        return cerezBellek;
    }
    function cerezIzni() { return !AYAR.cerezBandi || cerezSecimi() === 'kabul'; }
    function bantAc() {
        if (!cerezBant) return;
        cerezBant.hidden = false;
        d.body.classList.add('cerez-acik');
        requestAnimationFrame(function () { requestAnimationFrame(function () { cerezBant.classList.add('acik'); }); });
    }
    function bantKapat() {
        if (!cerezBant || cerezBant.hidden) return;
        cerezBant.classList.remove('acik');
        d.body.classList.remove('cerez-acik');
        setTimeout(function () { cerezBant.hidden = true; }, 400);
    }
    function cerezKaydet(secim) {
        cerezBellek = secim;
        try { w.localStorage.setItem(CEREZ_ANAHTAR, JSON.stringify({ secim: secim, zaman: Date.now() })); } catch (e) {}
        bantKapat();
        if (secim === 'kabul') haritaYukle();
    }
    function haritaYukle() {
        hepsi('.il-harita iframe[data-src]').forEach(function (f) {
            f.src = f.getAttribute('data-src');
            f.removeAttribute('data-src');
            f.hidden = false;
            var onay = f.parentNode.querySelector('.harita-onay');
            if (onay) onay.parentNode.removeChild(onay);
        });
    }
    hepsi('[data-harita-yukle]').forEach(function (b) { b.addEventListener('click', haritaYukle); });
    if (cerezIzni()) haritaYukle();
    if (cerezBant) {
        hepsi('[data-cerez]', cerezBant).forEach(function (b) {
            b.addEventListener('click', function () { cerezKaydet(b.getAttribute('data-cerez')); });
        });
        if (cerezSecimi() === null) setTimeout(bantAc, 600);
    }
    // "Çerez Tercihleri" bağlantıları (footer ve çerez politikası penceresi) sonradan eklenebildiği için belge düzeyinde dinlenir
    d.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-cerez-ayar]');
        if (!b) return;
        e.preventDefault();
        if (typeof yaziKapat === 'function') yaziKapat();
        bantAc();
    });

    /* ---------- Üst menü: kaydırınca arka plan ---------- */
    var ust = d.getElementById('ust');
    // Telefonda hero butonları ekrandayken sabit WhatsApp/Ara butonlarını gizle (üst üste binmesinler)
    var heroButonlari = function () { return d.querySelector('.hero-slayt.aktif .hero-butonlar') || d.querySelector('.hero-butonlar'); };
    function yuzenKontrol() {
        var b = heroButonlari(), gizle = false;
        if (b && w.innerWidth <= 900) { var r = b.getBoundingClientRect(); gizle = r.bottom > 0 && r.top < w.innerHeight; }
        d.body.classList.toggle('yuzen-gizli', gizle);
    }
    function ustKontrol() { ust.classList.toggle('kaydi', w.scrollY > 12); yuzenKontrol(); }
    w.addEventListener('resize', yuzenKontrol);
    w.addEventListener('scroll', ustKontrol, { passive: true });
    ustKontrol();

    /* ---------- Mobil çekmece menü ---------- */
    var cekmece = d.getElementById('cekmece');
    var menuAc = d.querySelector('.menu-ac');
    function cekmeceAc() {
        if (!menuAc) return;
        cekmece.classList.add('acik');
        cekmece.setAttribute('aria-hidden', 'false');
        menuAc.setAttribute('aria-expanded', 'true');
        d.body.classList.add('kilitli');
        setTimeout(function () { cekmece.querySelector('.cekmece-kapat').focus(); }, 50);
    }
    function cekmeceKapat() {
        if (!menuAc || !cekmece.classList.contains('acik')) return;
        cekmece.classList.remove('acik');
        cekmece.setAttribute('aria-hidden', 'true');
        menuAc.setAttribute('aria-expanded', 'false');
        d.body.classList.remove('kilitli');
    }
    if (menuAc) menuAc.addEventListener('click', cekmeceAc);
    hepsi('[data-kapat]', cekmece).forEach(function (el) { el.addEventListener('click', cekmeceKapat); });
    // Menü yazıları tek satıra sığmıyorsa hamburger menüye geç
    var menuEl = d.querySelector('.menu');
    function menuSigdir() {
        if (!menuEl || !menuAc) return;
        ust.classList.remove('sigmiyor');
        if (getComputedStyle(menuEl).display === 'none') return;
        if (menuEl.scrollWidth > menuEl.clientWidth + 2) ust.classList.add('sigmiyor');
    }
    menuSigdir();
    if (d.fonts && d.fonts.ready) d.fonts.ready.then(menuSigdir);
    w.addEventListener('resize', function () {
        menuSigdir();
        if (menuAc && getComputedStyle(menuAc).display === 'none') cekmeceKapat();
    });

    /* ---------- Aktif menü bağlantısı (ekranın üst kısmındaki bölüm) ---------- */
    var menuLinkleri = hepsi('.menu a[href^="#"], .cekmece nav a[href^="#"]');
    var bolumler = hepsi('section[id]');
    var aktifBolum = null, aktifBekliyor = false;
    function aktifBul() {
        aktifBekliyor = false;
        var sinir = w.innerHeight * 0.35, bulunan = bolumler[0];
        bolumler.forEach(function (s) { if (s.getBoundingClientRect().top <= sinir) bulunan = s; });
        if (!bulunan || bulunan === aktifBolum) return;
        aktifBolum = bulunan;
        menuLinkleri.forEach(function (a) { a.classList.toggle('aktif', a.getAttribute('href') === '#' + bulunan.id); });
    }
    w.addEventListener('scroll', function () {
        if (!aktifBekliyor) { aktifBekliyor = true; requestAnimationFrame(aktifBul); }
    }, { passive: true });
    aktifBul();

    /* ---------- Kaydırınca beliren öğeler ---------- */
    var belirenler = hepsi('.reveal');
    if ('IntersectionObserver' in w && !azHareket) {
        var beliriGozcu = new IntersectionObserver(function (girdiler) {
            girdiler.forEach(function (g) {
                if (g.isIntersecting) { g.target.classList.add('gorunur'); beliriGozcu.unobserve(g.target); }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        belirenler.forEach(function (el) { beliriGozcu.observe(el); });
    } else {
        belirenler.forEach(function (el) { el.classList.add('gorunur'); });
    }

    /* ---------- Hero slayt gösterisi ---------- */
    var hero = d.querySelector('.hero');
    var slaytlar = hepsi('.hero-slayt', hero);
    if (slaytlar.length > 1) {
        var noktalar = hepsi('.hero-nokta', hero);
        var simdiki = 0;
        // Diğer slaytların fotoğrafları ilk fotoğrafı yavaşlatmasın diye sayfa yüklenince iner
        var slaytResmi = function (s) {
            var img = s.querySelector('img[data-src]');
            if (!img) return;
            if (img.getAttribute('data-srcset')) img.setAttribute('srcset', img.getAttribute('data-srcset'));
            img.setAttribute('src', img.getAttribute('data-src'));
            img.removeAttribute('data-src');
            img.removeAttribute('data-srcset');
        };
        if (d.readyState === 'complete') slaytlar.forEach(slaytResmi);
        else w.addEventListener('load', function () { slaytlar.forEach(slaytResmi); });
        var git = function (n) {
            n = (n + slaytlar.length) % slaytlar.length;
            if (n === simdiki) return;
            slaytResmi(slaytlar[n]);
            slaytlar[simdiki].classList.remove('aktif');
            slaytlar[simdiki].setAttribute('inert', '');
            noktalar[simdiki].classList.remove('aktif');
            simdiki = n;
            slaytlar[n].classList.add('aktif');
            slaytlar[n].removeAttribute('inert');
            noktalar[n].classList.add('aktif');
        };
        hero.querySelector('.hero-ok.onceki').addEventListener('click', function () { git(simdiki - 1); });
        hero.querySelector('.hero-ok.sonraki').addEventListener('click', function () { git(simdiki + 1); });
        noktalar.forEach(function (b, i) { b.addEventListener('click', function () { git(i); }); });

        // Otomatik geçiş: ilerleme çubuğu dolunca sonraki slayta geçer
        if (!azHareket) {
            hero.addEventListener('animationend', function (e) {
                if (e.target.classList.contains('dolum')) git(simdiki + 1);
            });
        }
        // Fare butonların üzerindeyken slayt değişmesin
        hepsi('.hero-butonlar, .hero-kontrol', hero).forEach(function (el) {
            el.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse') hero.classList.add('durdu'); });
            el.addEventListener('pointerleave', function () { hero.classList.remove('durdu'); });
        });

        // Parmakla kaydırma
        var basX = null, basY = 0;
        hero.addEventListener('touchstart', function (e) { basX = e.touches[0].clientX; basY = e.touches[0].clientY; }, { passive: true });
        hero.addEventListener('touchend', function (e) {
            if (basX === null) return;
            var dx = e.changedTouches[0].clientX - basX, dy = e.changedTouches[0].clientY - basY;
            if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.2) git(simdiki + (dx < 0 ? 1 : -1));
            basX = null;
        }, { passive: true });
    }

    /* ---------- Sayaçlar ---------- */
    var sayaclar = hepsi('[data-sayac]');
    function bicim(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function say(el) {
        var hedef = parseInt(el.getAttribute('data-sayac'), 10) || 0, bas = null, sure = 1800;
        function adim(t) {
            if (bas === null) bas = t;
            var p = Math.min((t - bas) / sure, 1);
            el.textContent = bicim(Math.round(hedef * (1 - Math.pow(1 - p, 3))));
            if (p < 1) requestAnimationFrame(adim);
        }
        requestAnimationFrame(adim);
    }
    if (sayaclar.length && 'IntersectionObserver' in w && !azHareket) {
        sayaclar.forEach(function (el) { el.textContent = '0'; });
        var sayacGozcu = new IntersectionObserver(function (girdiler) {
            girdiler.forEach(function (g) {
                if (g.isIntersecting) { say(g.target); sayacGozcu.unobserve(g.target); }
            });
        }, { threshold: 0.5 });
        sayaclar.forEach(function (el) { sayacGozcu.observe(el); });
    }

    /* ---------- VKİ hesaplama (Dünya Sağlık Örgütü yetişkin sınıflandırması) ---------- */
    var vkiForm = d.getElementById('vkiFormu');
    if (vkiForm) {
        var VKI_SINIF = [
            { ust: 18.5, ad: 'Zayıf', renk: '#4ea3e0', satir: 0 },
            { ust: 25, ad: 'Normal', renk: '#2f9e5b', satir: 1 },
            { ust: 30, ad: 'Fazla kilolu', renk: '#e0a020', satir: 2 },
            { ust: 35, ad: 'Obez (1. derece)', renk: '#ef7d3c', satir: 3 },
            { ust: 40, ad: 'Obez (2. derece)', renk: '#ef7d3c', satir: 3 },
            { ust: Infinity, ad: 'İleri derece obez', renk: '#d9443a', satir: 4 }
        ];
        var vkiBoy = d.getElementById('vkiBoy'), vkiKilo = d.getElementById('vkiKilo');
        var vkiSayi = vkiForm.querySelector('.vki-sayi'), vkiDurum = vkiForm.querySelector('.vki-durum');
        var vkiIsaret = vkiForm.querySelector('.vki-isaret'), vkiYorum = vkiForm.querySelector('.vki-yorum');
        var vkiBtn = vkiForm.querySelector('.vki-btn'), vkiSatirlar = hepsi('.vki-aralik li');
        var kaydiraclar = hepsi('.vki-kaydirac', vkiForm);
        var tr1 = function (n) { return n.toLocaleString('tr-TR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }); };
        var doluluk = function (r) { r.style.setProperty('--dolu', ((r.value - r.min) / (r.max - r.min) * 100) + '%'); };
        var vkiHesapla = function () {
            var boy = parseFloat(vkiBoy.value), kilo = parseFloat(vkiKilo.value);
            vkiSatirlar.forEach(function (li) { li.classList.remove('aktif'); });
            if (!(boy >= 100 && boy <= 250 && kilo >= 20 && kilo <= 350)) {
                vkiSayi.textContent = '–';
                vkiDurum.textContent = 'Boy ve kilonuzu girin';
                vkiDurum.style.removeProperty('--r');
                vkiYorum.textContent = 'Boy 100-250 cm, kilo 20-350 kg arasında olmalıdır.';
                return;
            }
            var m = boy / 100, vki = kilo / (m * m);
            var sinif = VKI_SINIF.filter(function (s) { return vki < s.ust; })[0];
            var alt = 18.5 * m * m, ust = 24.9 * m * m;
            vkiSayi.textContent = tr1(vki);
            vkiDurum.textContent = sinif.ad;
            vkiDurum.style.setProperty('--r', sinif.renk);
            vkiIsaret.style.setProperty('--konum', Math.min(Math.max((vki - 15) / 30, 0), 1) * 100 + '%');
            if (vkiSatirlar[sinif.satir]) vkiSatirlar[sinif.satir].classList.add('aktif');
            var aralik = 'Boyunuza göre sağlıklı kilo aralığı <b>' + tr1(alt) + ' – ' + tr1(ust) + ' kg</b>.';
            if (vki < 18.5) vkiYorum.innerHTML = aralik + ' Bu aralığa ulaşmak için yaklaşık <b>' + tr1(alt - kilo) + ' kg</b> almanız gerekiyor.';
            else if (vki < 25) vkiYorum.innerHTML = 'Tebrikler, kilonuz sağlıklı aralıkta. ' + aralik;
            else vkiYorum.innerHTML = aralik + ' Bu aralığa ulaşmak için yaklaşık <b>' + tr1(kilo - ust) + ' kg</b> vermeniz gerekiyor.';
            if (AYAR.wa && vkiBtn) {
                var mesaj = 'Merhaba, sitenizdeki VKİ hesaplamasını yaptım.\nBoy: ' + boy + ' cm\nKilo: ' + String(kilo).replace('.', ',') +
                    ' kg\nVKİ: ' + tr1(vki) + ' (' + sinif.ad + ')\nSize özel beslenme programı hakkında bilgi almak istiyorum.';
                vkiBtn.href = 'https://wa.me/' + AYAR.wa + '?text=' + encodeURIComponent(mesaj);
            }
        };
        kaydiraclar.forEach(function (r) {
            var girdi = d.getElementById(r.getAttribute('data-bagli'));
            doluluk(r);
            r.addEventListener('input', function () { girdi.value = r.value; doluluk(r); vkiHesapla(); });
            girdi.addEventListener('input', function () {
                var v = parseFloat(girdi.value);
                if (!isNaN(v)) { r.value = Math.min(Math.max(v, r.min), r.max); doluluk(r); }
                vkiHesapla();
            });
        });
        vkiForm.addEventListener('submit', function (e) { e.preventDefault(); });
        vkiHesapla();
    }

    /* ---------- Galeri: filtre ve büyütme penceresi ---------- */
    var galeriOgeleri = hepsi('.galeri-oge');
    var filtreler = hepsi('.galeri-filtre button');
    filtreler.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var filtre = btn.getAttribute('data-filtre');
            filtreler.forEach(function (b) {
                b.classList.toggle('aktif', b === btn);
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
            });
            galeriOgeleri.forEach(function (o) {
                var goster = filtre === 'hepsi' || o.getAttribute('data-tur') === filtre;
                o.hidden = !goster;
                o.classList.remove('giris');
                if (goster) { void o.offsetWidth; o.classList.add('giris'); }
            });
        });
    });

    var isik = d.getElementById('isik');
    var isikMedya = isik.querySelector('.isik-medya');
    var isikBaslik = isik.querySelector('.isik-baslik');
    var isikSayac = isik.querySelector('.isik-sayac');
    var gorunenler = [], sira = 0, oncekiOdak = null, kapatZaman = null;

    function isikGoster() {
        var o = gorunenler[sira];
        var tur = o.getAttribute('data-tur'), kaynak = o.getAttribute('data-kaynak'), baslik = o.getAttribute('data-baslik') || '';
        isikMedya.innerHTML = '';
        isikMedya.style.animation = 'none'; void isikMedya.offsetWidth; isikMedya.style.animation = '';
        if (tur === 'video' && !cerezIzni()) {
            // Çerez onayı yok: YouTube yüklenmez, ziyaretçiye seçenek sunulur
            var riza = d.createElement('div');
            riza.className = 'isik-video isik-riza';
            riza.style.backgroundImage = 'url("https://i.ytimg.com/vi/' + encodeURIComponent(kaynak) + '/hqdefault.jpg")';
            riza.innerHTML = '<div class="isik-riza-ic"><i class="fa-brands fa-youtube"></i>' +
                '<p>Bu video YouTube tarafından oynatılır ve YouTube çerez kullanabilir.</p>' +
                '<div class="isik-riza-btn"><button type="button" class="btn btn-ana">Kabul Et ve Oynat</button>' +
                '<a class="btn btn-beyaz" target="_blank" rel="noopener">YouTube\'da İzle</a></div></div>';
            riza.querySelector('a').href = 'https://www.youtube.com/watch?v=' + encodeURIComponent(kaynak);
            riza.querySelector('button').addEventListener('click', function () { cerezKaydet('kabul'); isikGoster(); isik.querySelector('.isik-kapat').focus(); });
            isikMedya.appendChild(riza);
        } else if (tur === 'video') {
            var kutu = d.createElement('div');
            kutu.className = 'isik-video';
            var iframe = d.createElement('iframe');
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(kaynak) + '?autoplay=1&rel=0&playsinline=1';
            iframe.title = baslik || 'Video';
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            kutu.appendChild(iframe);
            isikMedya.appendChild(kutu);
        } else {
            var img = d.createElement('img');
            var buyuk = o.getAttribute('data-buyuk');
            if (buyuk && buyuk !== kaynak) {
                img.onerror = function () { img.onerror = null; img.src = kaynak; };
                img.src = buyuk;
            } else {
                img.src = kaynak;
            }
            img.alt = baslik;
            isikMedya.appendChild(img);
        }
        isikBaslik.textContent = baslik;
        isikSayac.textContent = (sira + 1) + ' / ' + gorunenler.length;
        hepsi('.isik-ok', isik).forEach(function (b) { b.hidden = gorunenler.length < 2; });
    }
    function isikAc(oge) {
        gorunenler = galeriOgeleri.filter(function (o) { return !o.hidden; });
        sira = Math.max(0, gorunenler.indexOf(oge));
        oncekiOdak = d.activeElement;
        clearTimeout(kapatZaman);
        isik.hidden = false;
        requestAnimationFrame(function () { isik.classList.add('acik'); });
        d.body.classList.add('kilitli');
        isikGoster();
        isik.querySelector('.isik-kapat').focus();
    }
    function isikKapat() {
        if (isik.hidden) return;
        isik.classList.remove('acik');
        isikMedya.innerHTML = '';
        d.body.classList.remove('kilitli');
        kapatZaman = setTimeout(function () { isik.hidden = true; }, 300);
        if (oncekiOdak) oncekiOdak.focus();
    }
    function isikKaydir(yon) {
        if (gorunenler.length < 2) return;
        sira = (sira + yon + gorunenler.length) % gorunenler.length;
        isikGoster();
    }
    galeriOgeleri.forEach(function (o) {
        o.addEventListener('click', function (e) { e.preventDefault(); isikAc(o); });
    });
    isik.querySelector('.isik-kapat').addEventListener('click', isikKapat);
    isik.querySelector('.isik-ok.onceki').addEventListener('click', function () { isikKaydir(-1); });
    isik.querySelector('.isik-ok.sonraki').addEventListener('click', function () { isikKaydir(1); });
    isik.addEventListener('click', function (e) {
        if (e.target === isik || e.target.classList.contains('isik-icerik')) isikKapat();
    });
    var isikX = null;
    isik.addEventListener('touchstart', function (e) { isikX = e.touches[0].clientX; }, { passive: true });
    isik.addEventListener('touchend', function (e) {
        if (isikX === null) return;
        var dx = e.changedTouches[0].clientX - isikX;
        if (Math.abs(dx) > 50) isikKaydir(dx < 0 ? 1 : -1);
        isikX = null;
    }, { passive: true });

    /* ---------- Yorumlar kaydırıcısı ---------- */
    var ray = d.querySelector('.yorum-ray');
    var noktaKutu = d.querySelector('.yorum-noktalar');
    if (ray && noktaKutu) {
        var kartlar = hepsi('.yorum-kart', ray);
        var adimGenislik = function () {
            return kartlar.length > 1 ? kartlar[1].offsetLeft - kartlar[0].offsetLeft : ray.clientWidth;
        };
        var sayfaSayisi = function () {
            return Math.max(1, kartlar.length - Math.round(ray.clientWidth / adimGenislik()) + 1);
        };
        var noktaCiz = function () {
            var adet = sayfaSayisi();
            noktaKutu.innerHTML = '';
            for (var i = 0; i < adet; i++) {
                var b = d.createElement('button');
                b.type = 'button';
                b.setAttribute('aria-label', (i + 1) + '. yoruma git');
                (function (n) { b.addEventListener('click', function () { ray.scrollTo({ left: n * adimGenislik(), behavior: 'smooth' }); }); })(i);
                noktaKutu.appendChild(b);
            }
            noktaKutu.parentNode.hidden = adet < 2;
            noktaGuncelle();
        };
        var noktaGuncelle = function () {
            var aktif = Math.round(ray.scrollLeft / adimGenislik());
            hepsi('button', noktaKutu).forEach(function (b, i) { b.classList.toggle('aktif', i === aktif); });
        };
        var yorumKaydir = function (yon) {
            var son = ray.scrollWidth - ray.clientWidth - 4;
            if (yon > 0 && ray.scrollLeft >= son) ray.scrollTo({ left: 0, behavior: 'smooth' });
            else if (yon < 0 && ray.scrollLeft <= 4) ray.scrollTo({ left: ray.scrollWidth, behavior: 'smooth' });
            else ray.scrollBy({ left: yon * adimGenislik(), behavior: 'smooth' });
        };
        d.querySelector('.yorum-onceki').addEventListener('click', function () { yorumKaydir(-1); });
        d.querySelector('.yorum-sonraki').addEventListener('click', function () { yorumKaydir(1); });
        var kaydirmaZaman;
        ray.addEventListener('scroll', function () { clearTimeout(kaydirmaZaman); kaydirmaZaman = setTimeout(noktaGuncelle, 60); }, { passive: true });
        var boyutZaman;
        w.addEventListener('resize', function () { clearTimeout(boyutZaman); boyutZaman = setTimeout(noktaCiz, 150); });
        noktaCiz();
    }

    /* ---------- S.S.S. akordeon ---------- */
    hepsi('.sss-soru').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var oge = btn.parentNode, acilacak = !oge.classList.contains('acik');
            hepsi('.sss-oge.acik').forEach(function (o) {
                o.classList.remove('acik');
                o.querySelector('.sss-soru').setAttribute('aria-expanded', 'false');
            });
            if (acilacak) { oge.classList.add('acik'); btn.setAttribute('aria-expanded', 'true'); }
        });
    });

    /* ---------- Blog yazısı / KVKK / çerez politikası penceresi ---------- */
    var modal = d.getElementById('yaziModal');
    var modalIcerik = modal.querySelector('.modal-icerik');
    var modalOncekiOdak = null, modalZaman = null;
    var YASAL = { kvkk: 'kvkk-aydinlatma-metni', cerez: 'cerez-politikasi' }; // pencere adı -> adres (#...)
    function yaziAc(i, adresGuncelle) {
        pencereAc('yazi-sablon-' + i, adresGuncelle ? 'yazi-' + (i + 1) : '');
    }
    function pencereAc(sablonId, adres) {
        var sablon = d.getElementById(sablonId);
        if (!sablon) return;
        cekmeceKapat();
        modalOncekiOdak = d.activeElement;
        modalIcerik.innerHTML = '';
        modalIcerik.appendChild(sablon.content.cloneNode(true));
        modalIcerik.scrollTop = 0;
        clearTimeout(modalZaman);
        modal.hidden = false;
        requestAnimationFrame(function () { modal.classList.add('acik'); });
        d.body.classList.add('kilitli');
        modal.querySelector('.modal-kapat').focus();
        if (adres && history.replaceState) history.replaceState(null, '', '#' + adres);
    }
    function yaziKapat() {
        if (modal.hidden) return;
        modal.classList.remove('acik');
        d.body.classList.remove('kilitli');
        modalZaman = setTimeout(function () { modal.hidden = true; modalIcerik.innerHTML = ''; }, 350);
        if (history.replaceState && /^#(yazi-|kvkk-|cerez-)/.test(location.hash)) history.replaceState(null, '', location.pathname + location.search);
        if (modalOncekiOdak && d.body.contains(modalOncekiOdak)) modalOncekiOdak.focus();
    }
    hepsi('[data-yazi]').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); yaziAc(parseInt(el.getAttribute('data-yazi'), 10), true); });
    });
    // KVKK / çerez politikası bağlantıları (form onay kutusu, footer, çerez bandı)
    d.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('[data-metin]');
        if (!el) return;
        e.preventDefault();
        var ad = el.getAttribute('data-metin');
        pencereAc('sablon-' + ad, YASAL[ad]);
    });
    hepsi('[data-kapat]', modal).forEach(function (el) { el.addEventListener('click', yaziKapat); });
    // siteadresi.com/#yazi-2 veya #kvkk-aydinlatma-metni gibi paylaşılan linkler pencereyi doğrudan açar
    function yaziHashKontrol() {
        var m = location.hash.match(/^#yazi-(\d+)$/);
        if (m) { yaziAc(parseInt(m[1], 10) - 1, false); return; }
        for (var ad in YASAL) {
            if (location.hash === '#' + YASAL[ad]) pencereAc('sablon-' + ad, '');
        }
    }
    w.addEventListener('hashchange', yaziHashKontrol);
    yaziHashKontrol();

    /* ---------- Klavye kısayolları ---------- */
    d.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { isikKapat(); yaziKapat(); cekmeceKapat(); }
        if (!isik.hidden) {
            if (e.key === 'ArrowRight') isikKaydir(1);
            if (e.key === 'ArrowLeft') isikKaydir(-1);
        }
    });

    /* ---------- İletişim formu: WhatsApp / e-posta ile gönder ---------- */
    var form = d.getElementById('iletisimFormu');
    if (form) {
        if (form.elements.kvkk) {
            form.elements.kvkk.addEventListener('change', function () { this.closest('.onay-kutu').classList.remove('hata'); });
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var durum = form.querySelector('.form-durum');
            var deger = function (ad) { return form.elements[ad].value.trim(); };
            if (!deger('ad') || !deger('mesaj')) {
                durum.style.color = '#c0392b';
                durum.textContent = 'Lütfen adınızı ve mesajınızı yazın.';
                (deger('ad') ? form.elements.mesaj : form.elements.ad).focus();
                return;
            }
            var kvkkKutu = form.elements.kvkk;
            if (kvkkKutu && !kvkkKutu.checked) {
                durum.style.color = '#c0392b';
                durum.textContent = 'Lütfen KVKK Aydınlatma Metni onayını işaretleyin.';
                kvkkKutu.closest('.onay-kutu').classList.add('hata');
                kvkkKutu.focus();
                return;
            }
            var satirlar = ['Merhaba, web siteniz üzerinden ulaşıyorum.', '', 'Ad Soyad: ' + deger('ad')];
            if (deger('telefon')) satirlar.push('Telefon: ' + deger('telefon'));
            if (deger('eposta')) satirlar.push('E-posta: ' + deger('eposta'));
            satirlar.push('', deger('mesaj'));
            var mesaj = satirlar.join('\n');
            durum.style.color = '';
            if (AYAR.wa) {
                w.open('https://wa.me/' + AYAR.wa + '?text=' + encodeURIComponent(mesaj), '_blank', 'noopener');
                durum.textContent = 'Teşekkürler! Mesajınız WhatsApp\'ta hazırlandı, göndermeyi unutmayın.';
            } else if (AYAR.eposta) {
                location.href = 'mailto:' + AYAR.eposta + '?subject=' + encodeURIComponent('Web sitesi mesajı - ' + deger('ad')) + '&body=' + encodeURIComponent(mesaj);
                durum.textContent = 'Teşekkürler! E-posta uygulamanız açılıyor.';
            }
            form.reset();
        });
    }
})();
</script>
</body>
</html>
