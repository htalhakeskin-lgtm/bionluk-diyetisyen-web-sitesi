<?php
/*
 * ==============================================================
 *  YÖNETİM PANELİ - admin.php
 * --------------------------------------------------------------
 *  Sitedeki tüm yazılar bu panelden düzenlenir ve "Kaydet"
 *  denildiğinde veriler.txt dosyasına yazılır.
 *
 *  !!! GÜVENLİK: Aşağıdaki şifreyi mutlaka kendi şifrenizle değiştirin !!!
 * ==============================================================
 */
$YONETICI_SIFRE = 'admin123';

$VERI_DOSYASI = __DIR__ . '/veriler.txt';

/* ---------------- Oturum ---------------- */

$guvenliBaglanti = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
@ini_set('session.use_strict_mode', '1');
session_name('diyet_panel');
session_set_cookie_params(0, '/', '', $guvenliBaglanti, true);
session_start();

header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}
$TOKEN = $_SESSION['token'];

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

    if (substr($icerik, 0, 3) === "\xEF\xBB\xBF") {
        $icerik = substr($icerik, 3);
    }
    $veri = json_decode($icerik, true);
    return is_array($veri) ? $veri : array();
}

// Verileri veriler.txt dosyasına fopen / fwrite / fclose ile yazar
function veri_yaz($dosya, $veri)
{
    $bayraklar = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $bayraklar |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($veri, $bayraklar);
    if ($json === false) {
        return false;
    }

    // "c" kipi dosyayı kilitlemeden önce boşaltmaz; böylece o an siteye giren ziyaretçi yarım veri görmez
    $fp = @fopen($dosya, 'c');
    if (!$fp) {
        return false;
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    $yazilan = fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $yazilan === strlen($json);
}

function e($metin)
{
    return htmlspecialchars((string) $metin, ENT_QUOTES, 'UTF-8');
}

function temizle($metin)
{
    $metin = str_replace(array("\r\n", "\r"), "\n", (string) $metin);
    $metin = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $metin);
    return trim($metin);
}

// Alan tanımı: a('anahtar', 'Etiket', 'tip', 'ipucu', array(ek ayarlar))
function a($ad, $etiket, $tip = 'text', $ipucu = '', $ek = array())
{
    return array_merge(array('ad' => $ad, 'etiket' => $etiket, 'tip' => $tip, 'ipucu' => $ipucu, 'varsayilan' => ''), $ek);
}

/* ---------------- Panel şeması (sekmeler ve alanlar) ---------------- */

$MENU = array(
    'anasayfa'  => array('Ana Sayfa', 'fa-house'),
    'hizmetler' => array('Hizmetler', 'fa-hand-holding-heart'),
    'galeri'    => array('Galeri', 'fa-photo-film'),
    'hakkinda'  => array('Hakkında', 'fa-user'),
    'blog'      => array('Blog', 'fa-newspaper'),
    'yorumlar'  => array('Yorumlarımız', 'fa-comment-dots'),
    'sss'       => array('S.S.S.', 'fa-circle-question'),
    'iletisim'  => array('İletişim', 'fa-envelope'),
    'whatsapp'  => array('Whatsapp Linki', 'fa-whatsapp'),
);

$bolumBasliklari = array(
    a('ust_baslik', 'Üst Etiket', 'text', 'Başlığın üstündeki küçük yeşil yazı. Boş bırakılabilir.'),
    a('baslik', 'Bölüm Başlığı', 'text', 'Bir kelimeyi *yıldız* içine alırsanız yeşil italik görünür.'),
    a('aciklama', 'Kısa Açıklama', 'textarea', 'Başlığın altındaki açıklama. Boş bırakılabilir.'),
);

$SEKMELER = array(
    'genel' => array(
        'baslik' => 'Genel Ayarlar', 'ikon' => 'fa-sliders',
        'aciklama' => 'Site adı, ana renk, Google bilgileri ve WhatsApp numarası.',
        'kartlar' => array(
            array('baslik' => 'Site Kimliği', 'alanlar' => array(
                a('site_adi', 'Site Adı (Logo Yazısı)'),
                a('logo_ikon', 'Logo İkonu', 'ikon'),
                a('ana_renk', 'Sitenin Ana Rengi', 'renk', 'Butonlar, ikonlar ve vurgular bu renkte olur.'),
                a('sayfa_basligi', 'Tarayıcı Sekmesindeki Başlık', 'text', 'Google arama sonuçlarında da bu başlık görünür.'),
                a('site_aciklamasi', 'Site Açıklaması (Google için)', 'textarea', 'Arama sonuçlarında başlığın altında görünen kısa açıklama (150-160 karakter ideal).'),
            )),
            array('baslik' => 'WhatsApp', 'alanlar' => array(
                a('whatsapp', 'WhatsApp Numarası', 'text', 'Başında 90 olacak şekilde yazın. Örn: 905321234567'),
                a('whatsapp_mesaj', 'Hazır Mesaj', 'text', 'Ziyaretçi WhatsApp butonuna bastığında mesaj kutusunda hazır gelir.'),
            )),
            array('baslik' => 'Hız', 'alanlar' => array(
                a('gorsel_hizlandir', 'Fotoğrafları hızlandır (önerilir)', 'onay', 'Büyük fotoğraflar ücretsiz wsrv.nl görsel servisi üzerinden ekran boyutuna göre küçültülüp hızlı yüklenen WebP biçiminde gösterilir. Orijinal dosyalarınız değişmez; servis cevap vermezse orijinal fotoğraf gösterilir.'),
            )),
        ),
    ),
    'menu' => array(
        'baslik' => 'Menü', 'ikon' => 'fa-bars',
        'aciklama' => 'Üst menüdeki yazıları değiştirin veya gizleyin.',
        'kartlar' => array(),
    ),
    'hero' => array(
        'baslik' => 'Hero (Karşılama)', 'ikon' => 'fa-wand-magic-sparkles',
        'aciklama' => 'Sayfanın en üstündeki büyük kayan slaytlar.',
        'kartlar' => array(
            array('baslik' => 'Slaytlar', 'aciklama' => 'Slaytlar birkaç saniyede bir otomatik değişir. Sırayı oklarla değiştirebilirsiniz.', 'liste' => array(
                'ad' => 'slaytlar', 'etiket' => 'Slayt', 'ozet' => 'baslik', 'alanlar' => array(
                    a('ust_baslik', 'Üst Etiket', 'text', 'Başlığın üstündeki küçük yazı.'),
                    a('baslik', 'Başlık', 'text', 'Son kelime otomatik yeşil italik olur. İsterseniz *yıldız* ile kendiniz seçin.'),
                    a('aciklama', 'Açıklama', 'textarea'),
                    a('gorsel', 'Görsel Adresi (URL)', 'resim', 'Dikey veya kare fotoğraflar en iyi sonucu verir.'),
                    a('buton_yazi', 'Buton Yazısı', 'text', 'Boş bırakılırsa sadece WhatsApp butonu görünür.'),
                    a('buton_link', 'Buton Linki', 'text', 'Örn: #hakkinda, #iletisim, #galeri, #vki (VKİ hesaplama) veya https://...'),
                ),
            )),
            array('baslik' => 'Görselin Üzerindeki Küçük Kartlar', 'aciklama' => 'Tüm slaytlarda görselin köşelerinde görünür. Boş bırakılan kart gösterilmez.', 'alanlar' => array(
                a('kart1_sayi', '1. Kart - Sayı', 'text', 'Örn: 500+'),
                a('kart1_yazi', '1. Kart - Yazı', 'text', 'Örn: Mutlu Danışan'),
                a('kart2_sayi', '2. Kart - Sayı', 'text', 'Örn: %98'),
                a('kart2_yazi', '2. Kart - Yazı', 'text', 'Örn: Memnuniyet'),
            )),
        ),
    ),
    'hizmetler' => array(
        'baslik' => 'Hizmetler', 'ikon' => 'fa-hand-holding-heart',
        'aciklama' => 'Hizmet kartları ve bölüm başlığı.',
        'kartlar' => array(
            array('baslik' => 'Bölüm Başlığı', 'alanlar' => $bolumBasliklari),
            array('baslik' => 'Hizmet Kartları', 'liste' => array(
                'ad' => 'liste', 'etiket' => 'Hizmet', 'ozet' => 'baslik', 'alanlar' => array(
                    a('ikon', 'İkon', 'ikon', '', array('varsayilan' => 'fa-solid fa-leaf')),
                    a('baslik', 'Hizmet Adı'),
                    a('aciklama', 'Açıklama', 'textarea'),
                ),
            )),
        ),
    ),
    'vki' => array(
        'baslik' => 'VKİ Hesaplama', 'ikon' => 'fa-calculator',
        'aciklama' => 'Ziyaretçinin boy ve kilosuyla vücut kitle indeksini hesapladığı bölüm.',
        'kartlar' => array(
            array('baslik' => 'Bölüm', 'aciklama' => 'Bu bölüm menüde yer almaz. Hero butonlarına #vki yazarak bu bölüme bağlantı verebilirsiniz.', 'alanlar' => array(
                a('goster', 'VKİ hesaplama bölümünü sitede göster', 'onay'),
                a('ust_baslik', 'Üst Etiket', 'text', 'Başlığın üstündeki küçük yeşil yazı. Boş bırakılabilir.', array('varsayilan' => 'VKİ Hesaplama')),
                a('baslik', 'Bölüm Başlığı', 'text', 'Bir kelimeyi *yıldız* içine alırsanız yeşil italik görünür.', array('varsayilan' => 'Vücut Kitle *İndeksinizi* Hesaplayın')),
                a('aciklama', 'Kısa Açıklama', 'textarea', 'Başlığın altındaki açıklama. Boş bırakılabilir.'),
                a('buton_yazi', 'Buton Yazısı', 'text', 'Sonucu WhatsApp ile diyetisyene gönderen buton.', array('varsayilan' => 'Sonucumu Diyetisyene Gönder')),
                a('uyari', 'Bilgilendirme Notu', 'textarea', 'Hesaplayıcının altında küçük yazıyla görünür. Boş bırakılabilir.',
                    array('varsayilan' => 'VKİ, 18 yaş üstü yetişkinler için genel bir göstergedir. Kas oranı, yaş, hamilelik ve sağlık durumunuz kesin değerlendirme için ayrıca ele alınmalıdır.')),
            )),
        ),
    ),
    'istatistik' => array(
        'baslik' => 'İstatistikler', 'ikon' => 'fa-chart-simple',
        'aciklama' => 'Koyu yeşil şerit üzerindeki sayılar (sayılar ekranda 0\'dan yukarı sayar).',
        'kartlar' => array(
            array('baslik' => 'Bölüm', 'alanlar' => array(
                a('goster', 'Bu bölümü sitede göster', 'onay'),
                a('baslik', 'Bölüm Başlığı', 'text', 'Bir kelimeyi *yıldız* içine alırsanız vurgulu görünür.'),
            )),
            array('baslik' => 'Sayılar', 'liste' => array(
                'ad' => 'liste', 'etiket' => 'Sayı', 'ozet' => 'etiket', 'alanlar' => array(
                    a('on_ek', 'Ön Ek', 'text', 'Sayının önüne gelir. Örn: %'),
                    a('sayi', 'Sayı', 'text', 'Sadece rakam yazın. Örn: 1200'),
                    a('son_ek', 'Son Ek', 'text', 'Sayının arkasına gelir. Örn: +'),
                    a('etiket', 'Açıklama', 'text', 'Örn: Mutlu Danışan'),
                ),
            )),
        ),
    ),
    'galeri' => array(
        'baslik' => 'Galeri', 'ikon' => 'fa-photo-film',
        'aciklama' => 'Fotoğraflar ve YouTube videoları. Tıklanınca büyük pencerede açılır.',
        'kartlar' => array(
            array('baslik' => 'Bölüm Başlığı', 'alanlar' => $bolumBasliklari),
            array('baslik' => 'Galeri Öğeleri', 'aciklama' => 'Video için YouTube linkini yapıştırmanız yeterli; kapak resmi otomatik alınır.', 'liste' => array(
                'ad' => 'liste', 'etiket' => 'Öğe', 'ozet' => 'baslik', 'alanlar' => array(
                    a('tur', 'Tür', 'secim', '', array('secenekler' => array('resim' => 'Fotoğraf', 'video' => 'YouTube Videosu'), 'varsayilan' => 'resim')),
                    a('baslik', 'Başlık (isteğe bağlı)'),
                    a('adres', 'Fotoğraf Adresi veya YouTube Linki', 'resim'),
                ),
            )),
        ),
    ),
    'hakkinda' => array(
        'baslik' => 'Hakkında', 'ikon' => 'fa-user',
        'aciklama' => 'Diyetisyen tanıtım bölümü.',
        'kartlar' => array(
            array('baslik' => 'Tanıtım', 'alanlar' => array(
                a('baslik', 'Bölüm Etiketi', 'text', 'İsmin üstündeki küçük yeşil yazı.'),
                a('isim', 'Ad Soyad'),
                a('unvan', 'Unvan', 'text', 'Örn: Uzman Diyetisyen'),
                a('gorsel', 'Fotoğraf Adresi (URL)', 'resim'),
                a('metin', 'Tanıtım Yazısı', 'uzun', 'Paragraflar arasında bir boş satır bırakın.'),
                a('one_cikanlar', 'Öne Çıkan Maddeler', 'textarea', 'Her satıra bir madde yazın. Yanında onay işaretiyle listelenir.'),
            )),
            array('baslik' => 'Fotoğraf Üzerindeki Rozet', 'aciklama' => 'Boş bırakılırsa rozet gösterilmez.', 'alanlar' => array(
                a('rozet_sayi', 'Rozet Sayısı', 'text', 'Örn: 5+'),
                a('rozet_yazi', 'Rozet Yazısı', 'text', 'Örn: Yıllık Deneyim'),
            )),
        ),
    ),
    'blog' => array(
        'baslik' => 'Blog', 'ikon' => 'fa-newspaper',
        'aciklama' => 'Blog yazıları. "Devamını Oku" denince yazı açılır pencerede okunur.',
        'kartlar' => array(
            array('baslik' => 'Bölüm Başlığı', 'alanlar' => $bolumBasliklari),
            array('baslik' => 'Yazılar', 'liste' => array(
                'ad' => 'liste', 'etiket' => 'Yazı', 'ozet' => 'baslik', 'alanlar' => array(
                    a('baslik', 'Yazı Başlığı'),
                    a('tarih', 'Tarih', 'text', 'Örn: 5 Ekim 2026'),
                    a('ikon', 'İkon', 'ikon', 'Görsel eklenmezse kartta bu ikon gösterilir.', array('varsayilan' => 'fa-solid fa-apple-whole')),
                    a('gorsel', 'Kapak Görseli (isteğe bağlı)', 'resim'),
                    a('ozet', 'Kısa Özet', 'textarea', 'Kartta görünen 1-2 cümlelik özet.'),
                    a('icerik', 'Yazının Tamamı', 'uzun', 'Paragraflar arasında bir boş satır bırakın. **Kalın** yazmak için iki yıldız kullanın.'),
                ),
            )),
        ),
    ),
    'yorumlar' => array(
        'baslik' => 'Yorumlar', 'ikon' => 'fa-comment-dots',
        'aciklama' => 'Danışan yorumları (yana kaydırılabilir kartlar).',
        'kartlar' => array(
            array('baslik' => 'Bölüm Başlığı', 'alanlar' => $bolumBasliklari),
            array('baslik' => 'Yorumlar', 'liste' => array(
                'ad' => 'liste', 'etiket' => 'Yorum', 'ozet' => 'isim', 'alanlar' => array(
                    a('isim', 'Danışan Adı'),
                    a('yorum', 'Yorum', 'textarea'),
                ),
            )),
        ),
    ),
    'sss' => array(
        'baslik' => 'S.S.S.', 'ikon' => 'fa-circle-question',
        'aciklama' => 'Sık sorulan sorular (açılır kapanır liste).',
        'kartlar' => array(
            array('baslik' => 'Bölüm Başlığı', 'alanlar' => $bolumBasliklari),
            array('baslik' => 'Sorular', 'liste' => array(
                'ad' => 'liste', 'etiket' => 'Soru', 'ozet' => 'soru', 'alanlar' => array(
                    a('soru', 'Soru'),
                    a('cevap', 'Cevap', 'textarea'),
                ),
            )),
        ),
    ),
    'iletisim' => array(
        'baslik' => 'İletişim', 'ikon' => 'fa-envelope',
        'aciklama' => 'Adres, telefon, çalışma saatleri ve harita. Form mesajları WhatsApp\'a iletilir.',
        'kartlar' => array(
            array('baslik' => 'Bölüm Başlığı', 'alanlar' => array_merge($bolumBasliklari, array(
                a('form_baslik', 'Form Başlığı', 'text', 'Örn: Bizimle İletişime Geçin'),
            ))),
            array('baslik' => 'İletişim Bilgileri', 'alanlar' => array(
                a('telefon', 'Telefon'),
                a('eposta', 'E-posta'),
                a('adres', 'Adres', 'textarea'),
                a('saatler', 'Çalışma Saatleri', 'textarea', 'Her satıra bir gün/saat yazın.'),
                a('harita_link', 'Google Haritalar Linki', 'text', 'Google Haritalar\'da "Paylaş" > "Bağlantıyı kopyala" ile aldığınız link. Boşsa adresten oluşturulur.'),
                a('harita_goster', 'Adresin haritasını sayfada göster', 'onay'),
                a('arama_butonu', 'Telefonda sabit "Hemen Ara" butonunu göster', 'onay', 'Telefon ve tablette sağ altta, WhatsApp butonunun üstünde görünür; dokununca yukarıdaki numara aranır.'),
            )),
        ),
    ),
    'footer' => array(
        'baslik' => 'Alt Bilgi', 'ikon' => 'fa-grip-lines',
        'aciklama' => 'Sayfanın en altındaki bölüm ve sosyal medya hesapları.',
        'kartlar' => array(
            array('baslik' => 'Alt Bilgi', 'alanlar' => array(
                a('aciklama', 'Açıklama Yazısı', 'textarea'),
                a('telif', 'Telif Yazısı', 'text', 'Örn: © 2026 Dyt. Ayşe Kaya. Tüm hakları saklıdır.'),
            )),
            array('baslik' => 'Sosyal Medya', 'aciklama' => 'Hesabınızın tam linkini yazın. Boş bırakılan hesap gösterilmez.', 'alanlar' => array(
                a('instagram', 'Instagram'),
                a('facebook', 'Facebook'),
                a('youtube', 'YouTube'),
                a('linkedin', 'LinkedIn'),
                a('tiktok', 'TikTok'),
                a('x', 'X (Twitter)'),
            )),
        ),
    ),
    'kvkk' => array(
        'baslik' => 'KVKK & Çerezler', 'ikon' => 'fa-shield-halved',
        'aciklama' => 'Kişisel verilerin korunması aydınlatma metni, çerez politikası ve çerez bandı.',
        'kartlar' => array(
            array('baslik' => 'Çerez Bandı', 'aciklama' => 'Ziyaretçi "Kabul Et" demeden Google Haritalar ve YouTube videoları yüklenmez; onların yerine "göster" butonu çıkar. Tercih 12 ay hatırlanır.', 'alanlar' => array(
                a('bant_goster', 'Çerez bandını göster', 'onay', 'Kapatırsanız harita ve videolar onay sorulmadan yüklenir.'),
                a('bant_baslik', 'Bant Başlığı', 'text', '', array('varsayilan' => 'Çerez Tercihleriniz')),
                a('bant_metni', 'Bant Metni', 'textarea', 'Metnin sonuna "Çerez Politikası" bağlantısı otomatik eklenir.',
                    array('varsayilan' => 'Sitemiz reklam veya takip çerezi kullanmaz. Onay verirseniz harita ve videolar için Google ve YouTube çerezleri kullanılabilir.')),
            )),
            array('baslik' => 'İletişim Formu Onayı', 'alanlar' => array(
                a('form_onay', 'Onay Kutusu Yazısı', 'textarea', '[metin] yazan yerde "KVKK Aydınlatma Metni" bağlantısı görünür. Aydınlatma metni boşsa onay kutusu gösterilmez.',
                    array('varsayilan' => "[metin]'ni okudum; mesajımda paylaştığım bilgilerin bu kapsamda işlenmesini kabul ediyorum.")),
            )),
            array('baslik' => 'Metinler', 'aciklama' => 'Metinlerde {site_adi}, {adres}, {telefon} ve {eposta} yazan yerlere sitedeki güncel bilgiler otomatik gelir. Paragraflar arasında boş satır bırakın, **kalın** yazmak için iki yıldız kullanın. Metinler genel bir şablondur; yayına almadan önce bir hukukçuya kontrol ettirmeniz önerilir.', 'alanlar' => array(
                a('aydinlatma_metni', 'KVKK Aydınlatma Metni', 'uzun', 'Boş bırakılırsa footer bağlantısı ve form onay kutusu gizlenir. Doğrudan bağlantı: siteadresiniz.com/#kvkk-aydinlatma-metni', array('satir' => 18)),
                a('cerez_metni', 'Çerez Politikası', 'uzun', 'Boş bırakılırsa bağlantısı gizlenir. Doğrudan bağlantı: siteadresiniz.com/#cerez-politikasi', array('satir' => 14)),
            )),
        ),
    ),
);

$IKONLAR = array(
    'fa-leaf', 'fa-seedling', 'fa-apple-whole', 'fa-carrot', 'fa-lemon', 'fa-pepper-hot',
    'fa-bowl-food', 'fa-utensils', 'fa-fish', 'fa-egg', 'fa-wheat-awn', 'fa-glass-water',
    'fa-mug-hot', 'fa-cookie-bite', 'fa-weight-scale', 'fa-scale-balanced', 'fa-ruler', 'fa-heart-pulse',
    'fa-heart', 'fa-stethoscope', 'fa-user-doctor', 'fa-notes-medical', 'fa-pills', 'fa-droplet',
    'fa-person-running', 'fa-person-walking', 'fa-dumbbell', 'fa-baby', 'fa-children', 'fa-person-pregnant',
    'fa-laptop-medical', 'fa-video', 'fa-mobile-screen', 'fa-calendar-check', 'fa-clipboard-list', 'fa-chart-line',
    'fa-trophy', 'fa-star', 'fa-bolt', 'fa-fire', 'fa-brain', 'fa-bed',
    'fa-sun', 'fa-moon', 'fa-spa', 'fa-face-smile', 'fa-hand-holding-heart', 'fa-comments',
);

/* ---------------- Kaydedilen formu veriye dönüştür ---------------- */

function alan_degeri($gelen, $alan)
{
    $ham = isset($gelen[$alan['ad']]) && is_scalar($gelen[$alan['ad']]) ? (string) $gelen[$alan['ad']] : '';
    switch ($alan['tip']) {
        case 'onay':
            return $ham === '1';
        case 'renk':
            $ham = strtolower(trim($ham));
            return preg_match('/^#[0-9a-f]{6}$/', $ham) ? $ham : '#1a8050';
        case 'secim':
            return array_key_exists($ham, $alan['secenekler']) ? $ham : $alan['varsayilan'];
        case 'ikon':
            return trim(preg_replace('/[^a-z0-9 \-]/i', '', $ham));
        default:
            return temizle($ham);
    }
}

function veri_topla($sekmeler, $menu, $post)
{
    $veri = array();
    foreach ($sekmeler as $sekme => $tanim) {
        if ($sekme === 'menu') {
            $veri['menu'] = array();
            foreach ($menu as $k => $m) {
                $gelen = isset($post['menu'][$k]) && is_array($post['menu'][$k]) ? $post['menu'][$k] : array();
                $yazi = isset($gelen['yazi']) && is_scalar($gelen['yazi']) ? temizle($gelen['yazi']) : '';
                $veri['menu'][$k] = array('yazi' => $yazi !== '' ? $yazi : $m[0], 'goster' => !empty($gelen['goster']));
            }
            continue;
        }
        $gelen = isset($post[$sekme]) && is_array($post[$sekme]) ? $post[$sekme] : array();
        $veri[$sekme] = array();
        foreach ($tanim['kartlar'] as $kart) {
            if (isset($kart['alanlar'])) {
                foreach ($kart['alanlar'] as $alan) {
                    $veri[$sekme][$alan['ad']] = alan_degeri($gelen, $alan);
                }
            }
            if (isset($kart['liste'])) {
                $liste = $kart['liste'];
                $ogeler = isset($gelen[$liste['ad']]) && is_array($gelen[$liste['ad']]) ? $gelen[$liste['ad']] : array();
                $veri[$sekme][$liste['ad']] = array();
                foreach ($ogeler as $oge) {
                    if (!is_array($oge)) {
                        continue;
                    }
                    $kayit = array();
                    $dolu = false;
                    foreach ($liste['alanlar'] as $alan) {
                        $kayit[$alan['ad']] = alan_degeri($oge, $alan);
                        if (!in_array($alan['tip'], array('secim', 'ikon')) && $kayit[$alan['ad']] !== '') {
                            $dolu = true;
                        }
                    }
                    if ($dolu) { // tamamen boş bırakılan öğeler kaydedilmez
                        $veri[$sekme][$liste['ad']][] = $kayit;
                    }
                }
            }
        }
    }
    return $veri;
}

/* ---------------- Form alanlarını çiz ---------------- */

function alan_html($isim, $alan, $deger)
{
    $id = 'a_' . trim(preg_replace('/[^a-z0-9_]+/i', '_', $isim), '_');
    $tip = $alan['tip'];
    $tam = in_array($tip, array('textarea', 'uzun', 'resim')) ? ' tam' : '';
    $h = '<div class="alan' . $tam . '">';

    if ($tip === 'onay') {
        $h .= '<label class="anahtar"><input type="checkbox" name="' . e($isim) . '" value="1"' . ($deger ? ' checked' : '') . '>'
            . '<span class="kaydirac" aria-hidden="true"></span><span>' . e($alan['etiket']) . '</span></label>';
    } else {
        $h .= '<label for="' . $id . '">' . e($alan['etiket']) . '</label>';
        $ozet = !empty($alan['ozet']) ? ' data-ozet' : '';
        switch ($tip) {
            case 'textarea':
            case 'uzun':
                $satir = isset($alan['satir']) ? (int) $alan['satir'] : ($tip === 'uzun' ? 9 : 3);
                $h .= '<textarea id="' . $id . '" name="' . e($isim) . '" rows="' . $satir . '"' . $ozet . '>' . e($deger) . '</textarea>';
                break;
            case 'renk':
                $renk = preg_match('/^#[0-9a-f]{6}$/i', (string) $deger) ? strtolower($deger) : '#1a8050';
                $h .= '<div class="renk-kutu"><input type="color" value="' . e($renk) . '" aria-label="Renk seçici">'
                    . '<input type="text" id="' . $id . '" name="' . e($isim) . '" value="' . e($renk) . '" maxlength="7" spellcheck="false"></div>';
                break;
            case 'ikon':
                $h .= '<div class="ikon-kutu"><span class="ikon-onizle"><i class="' . e(ikon_sinif($deger)) . '"></i></span>'
                    . '<input type="text" id="' . $id . '" name="' . e($isim) . '" value="' . e($deger) . '" spellcheck="false" placeholder="fa-solid fa-leaf">'
                    . '<button type="button" class="btn btn-kucuk" data-ikon-sec><i class="fa-solid fa-icons"></i> Seç</button></div>';
                break;
            case 'resim':
                $h .= '<div class="resim-kutu"><span class="onizle"><img alt=""><i class="fa-regular fa-image"></i></span>'
                    . '<input type="text" id="' . $id . '" name="' . e($isim) . '" value="' . e($deger) . '" placeholder="https://..." spellcheck="false"' . $ozet . '></div>';
                break;
            case 'secim':
                $h .= '<select id="' . $id . '" name="' . e($isim) . '"' . ($alan['ad'] === 'tur' ? ' data-tur' : '') . '>';
                foreach ($alan['secenekler'] as $deger2 => $yazi) {
                    $h .= '<option value="' . e($deger2) . '"' . ((string) $deger === (string) $deger2 ? ' selected' : '') . '>' . e($yazi) . '</option>';
                }
                $h .= '</select>';
                break;
            default:
                $h .= '<input type="text" id="' . $id . '" name="' . e($isim) . '" value="' . e($deger) . '"' . $ozet . '>';
        }
    }
    if ($alan['ipucu'] !== '') {
        $h .= '<small>' . e($alan['ipucu']) . '</small>';
    }
    return $h . '</div>';
}

function ikon_sinif($sinif)
{
    $sinif = trim(preg_replace('/[^a-z0-9 \-]/i', '', (string) $sinif));
    if ($sinif === '') {
        return 'fa-solid fa-leaf';
    }
    return preg_match('/\bfa-(solid|regular|brands)\b/', $sinif) ? $sinif : 'fa-solid ' . $sinif;
}

function oge_html($sekme, $liste, $anahtar, $oge, $sira)
{
    $ozetMetni = isset($oge[$liste['ozet']]) ? $oge[$liste['ozet']] : '';
    $h = '<div class="oge">';
    $h .= '<div class="oge-bas"><span class="oge-no">' . e($liste['etiket']) . ' <b>' . $sira . '</b></span>'
        . '<span class="oge-ozet">' . e($ozetMetni) . '</span>'
        . '<span class="oge-btn">'
        . '<button type="button" data-yukari title="Yukarı taşı" aria-label="Yukarı taşı"><i class="fa-solid fa-arrow-up"></i></button>'
        . '<button type="button" data-asagi title="Aşağı taşı" aria-label="Aşağı taşı"><i class="fa-solid fa-arrow-down"></i></button>'
        . '<button type="button" data-sil class="sil" title="Sil" aria-label="Sil"><i class="fa-regular fa-trash-can"></i></button>'
        . '</span></div><div class="alanlar">';
    foreach ($liste['alanlar'] as $alan) {
        if ($alan['ad'] === $liste['ozet']) {
            $alan['ozet'] = true;
        }
        $deger = is_array($oge) && isset($oge[$alan['ad']]) ? $oge[$alan['ad']] : $alan['varsayilan'];
        $h .= alan_html($sekme . '[' . $liste['ad'] . '][' . $anahtar . '][' . $alan['ad'] . ']', $alan, $deger);
    }
    return $h . '</div></div>';
}

/* ---------------- Çıkış ---------------- */

if (isset($_GET['cikis'])) {
    if (isset($_GET['t']) && hash_equals($TOKEN, (string) $_GET['t'])) {
        $_SESSION = array();
        session_destroy();
    }
    header('Location: admin.php');
    exit;
}

/* ---------------- Giriş ---------------- */

$V = veri_oku($VERI_DOSYASI);
$ANA_RENK = isset($V['genel']['ana_renk']) && preg_match('/^#[0-9a-f]{6}$/i', $V['genel']['ana_renk']) ? $V['genel']['ana_renk'] : '#1a8050';
$SITE_ADI = isset($V['genel']['site_adi']) && is_string($V['genel']['site_adi']) && $V['genel']['site_adi'] !== '' ? $V['genel']['site_adi'] : 'Web Sitesi';

$girisHata = '';
if (empty($_SESSION['giris'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sifre']) && is_string($_POST['sifre'])) {
        if (hash_equals((string) $YONETICI_SIFRE, (string) $_POST['sifre'])) {
            session_regenerate_id(true);
            $_SESSION['giris'] = true;
            header('Location: admin.php');
            exit;
        }
        sleep(1); // şifre denemelerini yavaşlatır
        $girisHata = 'Şifre hatalı, lütfen tekrar deneyin.';
    }
}

/* ---------------- Kaydet ---------------- */

$hataMesaji = '';
if (!empty($_SESSION['giris']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kaydet'])) {
    $sekme = isset($_POST['sekme']) && is_string($_POST['sekme']) && isset($SEKMELER[$_POST['sekme']]) ? $_POST['sekme'] : 'genel';
    if (!isset($_POST['token']) || !hash_equals($TOKEN, (string) $_POST['token'])) {
        $hataMesaji = 'Oturum süreniz dolmuş olabilir. Sayfayı yenileyip tekrar deneyin.';
    } else {
        // Şemada olmayan eski veriler korunur, panel alanları güncellenir
        $yeni = array_replace($V, veri_topla($SEKMELER, $MENU, $_POST));
        if (veri_yaz($VERI_DOSYASI, $yeni)) {
            header('Location: admin.php?kaydedildi=1#' . $sekme);
            exit;
        }
        $hataMesaji = 'Kaydedilemedi! veriler.txt dosyasına yazılamıyor. Hosting dosya yöneticisinden veriler.txt dosyasının izinlerini 644 (olmazsa 666) yapın.';
    }
}

$dosyaVar = is_file($VERI_DOSYASI);
$yazilabilir = $dosyaVar ? is_writable($VERI_DOSYASI) : is_writable(__DIR__);
$dosyaBozuk = $dosyaVar && empty($V) && filesize($VERI_DOSYASI) > 0;

function stil($ana)
{
    ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
<style>
:root {
    --ana: <?= e($ana) ?>;
    --koyu: #10251a;
    --zemin: #f2f5f1;
    --cizgi: #e2e7e0;
    --murekkep: #15261c;
    --metin: #5a665f;
    --soluk: #8b958f;
    --kirmizi: #d64545;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
[hidden] { display: none !important; }
body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; font-size: 15px; line-height: 1.6; color: var(--metin); background: var(--zemin); -webkit-font-smoothing: antialiased; }
a { color: inherit; text-decoration: none; }
button, input, select, textarea { font: inherit; color: inherit; }
button { cursor: pointer; background: none; border: 0; }
h1, h2, h3 { color: var(--murekkep); line-height: 1.25; }
:focus-visible { outline: 3px solid color-mix(in srgb, var(--ana) 45%, transparent); outline-offset: 2px; }

.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 11px 20px; border-radius: 12px; font-weight: 700; font-size: 14.5px; white-space: nowrap;
    transition: background .2s, box-shadow .2s, color .2s, transform .2s;
}
.btn-ana { background: var(--ana); color: #fff; box-shadow: 0 8px 18px -8px var(--ana); }
.btn-ana:hover { filter: brightness(.92); transform: translateY(-1px); }
.btn-cizgi { background: #fff; color: var(--murekkep); box-shadow: inset 0 0 0 1.5px var(--cizgi); }
.btn-cizgi:hover { box-shadow: inset 0 0 0 1.5px var(--ana); color: var(--ana); }
.btn-kucuk { padding: 0 14px; height: 44px; border-radius: 11px; background: #fff; box-shadow: inset 0 0 0 1.5px var(--cizgi); font-size: 13.5px; color: var(--murekkep); }
.btn-kucuk:hover { box-shadow: inset 0 0 0 1.5px var(--ana); color: var(--ana); }
.logo-ikon { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; border-radius: 14px 5px 14px 5px; background: var(--ana); color: #fff; font-size: 17px; }

/* Giriş ekranı */
.giris-sayfa {
    min-height: 100vh; display: grid; place-items: center; padding: 24px;
    background: radial-gradient(circle at 15% 10%, color-mix(in srgb, var(--ana) 18%, transparent), transparent 45%), radial-gradient(circle at 90% 90%, rgba(238, 143, 94, .16), transparent 45%), var(--zemin);
}
.giris-kart { width: min(420px, 100%); background: #fff; border-radius: 26px; padding: 40px 36px 34px; box-shadow: 0 30px 70px -35px rgba(16, 37, 26, .45); }
.giris-kart .logo-ikon { width: 56px; height: 56px; font-size: 22px; margin-bottom: 22px; }
.giris-kart h1 { font-size: 1.6rem; }
.giris-kart > p { margin: 4px 0 26px; }
.sifre-kutu { position: relative; }
.sifre-kutu input { padding-right: 52px; }
.sifre-goz { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 40px; height: 40px; border-radius: 10px; color: var(--soluk); }
.sifre-goz:hover { color: var(--ana); }
.giris-kart .btn { width: 100%; margin-top: 18px; padding: 14px; font-size: 15px; }
.giris-hata { margin-bottom: 18px; padding: 12px 14px; border-radius: 12px; background: #fdecec; color: var(--kirmizi); font-weight: 600; font-size: 14px; }
.giris-geri { display: inline-flex; gap: 8px; align-items: center; margin-top: 22px; font-weight: 600; font-size: 14px; color: var(--soluk); }
.giris-geri:hover { color: var(--ana); }

/* Form alanları */
.alan { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.alan.tam { grid-column: 1 / -1; }
.alan > label { font-weight: 700; font-size: 13.5px; color: var(--murekkep); }
.alan small { font-size: 12.5px; color: var(--soluk); line-height: 1.45; }
.alan input[type=text], .alan input[type=password], .alan textarea, .alan select {
    width: 100%; height: 46px; padding: 0 14px;
    font-size: 15px; color: var(--murekkep);
    background: #fbfcfa; border: 1.5px solid var(--cizgi); border-radius: 12px;
    outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
}
.alan textarea { height: auto; padding: 12px 14px; resize: vertical; line-height: 1.6; }
.alan select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a665f' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; cursor: pointer; }
.alan input:focus, .alan textarea:focus, .alan select:focus { background: #fff; border-color: var(--ana); box-shadow: 0 0 0 4px color-mix(in srgb, var(--ana) 14%, transparent); }

.renk-kutu, .ikon-kutu, .resim-kutu { display: flex; align-items: center; gap: 10px; }
.renk-kutu input[type=color] { width: 54px; height: 46px; flex: 0 0 54px; padding: 4px; border: 1.5px solid var(--cizgi); border-radius: 12px; background: #fff; cursor: pointer; }
.renk-kutu input[type=text] { font-family: ui-monospace, Consolas, monospace; text-transform: lowercase; }
.ikon-onizle { width: 46px; height: 46px; flex: 0 0 46px; display: grid; place-items: center; border-radius: 12px; background: color-mix(in srgb, var(--ana) 12%, #fff); color: var(--ana); font-size: 19px; }
.ikon-kutu input { font-family: ui-monospace, Consolas, monospace; font-size: 13.5px !important; }
.resim-kutu .onizle {
    position: relative; width: 78px; height: 58px; flex: 0 0 78px; overflow: hidden;
    display: grid; place-items: center; border-radius: 10px;
    background: #eef1ec; color: var(--soluk); font-size: 20px; border: 1.5px solid var(--cizgi);
}
.resim-kutu .onizle img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.resim-kutu.bos .onizle img, .resim-kutu.hata .onizle img { display: none; }
.resim-kutu.hata .onizle { background: #fdecec; color: var(--kirmizi); border-color: #f5c6c6; }
.resim-kutu.hata .onizle i::before { content: "\f071"; font-family: "Font Awesome 6 Free"; font-weight: 900; }

.anahtar { display: inline-flex; align-items: center; gap: 12px; cursor: pointer; font-weight: 700; font-size: 14px; color: var(--murekkep); user-select: none; }
.anahtar input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.kaydirac { position: relative; width: 44px; height: 26px; flex: 0 0 44px; border-radius: 999px; background: #cfd6cd; transition: background .25s; }
.kaydirac::after { content: ""; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 2px 4px rgba(0, 0, 0, .2); transition: transform .25s; }
.anahtar input:checked + .kaydirac { background: var(--ana); }
.anahtar input:checked + .kaydirac::after { transform: translateX(18px); }
.anahtar input:focus-visible + .kaydirac { outline: 3px solid color-mix(in srgb, var(--ana) 45%, transparent); outline-offset: 2px; }

/* Panel yerleşimi */
.panel { display: grid; grid-template-columns: 268px minmax(0, 1fr); min-height: 100vh; }
.yan { position: sticky; top: 0; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; background: var(--koyu); color: rgba(255, 255, 255, .72); padding: 22px 16px; }
.yan-logo { display: flex; align-items: center; gap: 12px; padding: 4px 8px 22px; margin-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, .08); }
.yan-logo strong { display: block; color: #fff; font-size: 15px; }
.yan-logo small { display: block; font-size: 12.5px; color: rgba(255, 255, 255, .55); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px; }
.yan-menu { display: flex; flex-direction: column; gap: 2px; }
.yan-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 11px; font-weight: 600; font-size: 14.5px; transition: background .2s, color .2s; }
.yan-menu a i { width: 20px; text-align: center; font-size: 15px; opacity: .75; }
.yan-menu a:hover { background: rgba(255, 255, 255, .06); color: #fff; }
.yan-menu a.aktif { background: var(--ana); color: #fff; }
.yan-menu a.aktif i { opacity: 1; }
.yan-alt { margin-top: auto; padding-top: 18px; display: grid; gap: 4px; border-top: 1px solid rgba(255, 255, 255, .08); }
.yan-alt a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 11px; font-weight: 600; font-size: 14px; }
.yan-alt a:hover { background: rgba(255, 255, 255, .06); color: #fff; }
.yan-alt a i { width: 20px; text-align: center; }

.icerik { min-width: 0; padding: 0 36px 60px; }
.ust-bar {
    position: sticky; top: 0; z-index: 20;
    display: flex; align-items: center; justify-content: space-between; gap: 20px;
    margin: 0 -36px 26px; padding: 18px 36px;
    background: rgba(242, 245, 241, .9); -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--cizgi);
}
.ust-bar h1 { font-size: 1.45rem; }
.ust-bar p { font-size: 14px; margin-top: 2px; }
.ust-eylem { display: flex; gap: 10px; flex-shrink: 0; }
.kirli-isaret { display: none; width: 8px; height: 8px; border-radius: 50%; background: #f4b33f; }
.kirli .kirli-isaret { display: inline-block; }

.uyari { display: flex; gap: 12px; align-items: flex-start; padding: 14px 18px; border-radius: 14px; margin-bottom: 18px; font-weight: 600; font-size: 14px; line-height: 1.5; }
.uyari i { margin-top: 3px; }
.uyari.sari { background: #fff6dd; color: #7a5600; }
.uyari.kirmizi { background: #fdecec; color: #a12d2d; }
.uyari code { background: rgba(0, 0, 0, .06); padding: 1px 6px; border-radius: 6px; font-size: 13px; }

.js .sekme { display: none; }
.js .sekme.aktif { display: block; animation: belir .3s ease; }
@keyframes belir { from { opacity: 0; transform: translateY(6px); } }
.sekme-baslik { display: none; font-size: 1.2rem; margin: 30px 0 14px; }
html:not(.js) .sekme-baslik { display: block; }

.kart { background: #fff; border: 1px solid var(--cizgi); border-radius: 20px; padding: 26px 28px 28px; margin-bottom: 20px; max-width: 980px; }
.kart-bas { margin-bottom: 20px; }
.kart-bas h2 { font-size: 1.08rem; }
.kart-bas p { font-size: 13.5px; color: var(--soluk); margin-top: 3px; }
.alanlar { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 20px; }

.liste { display: flex; flex-direction: column; gap: 14px; }
.oge { border: 1.5px solid var(--cizgi); border-radius: 16px; background: #fff; animation: belir .3s ease; }
.oge-bas { display: flex; align-items: center; gap: 12px; padding: 10px 10px 10px 16px; background: #f7f9f6; border-bottom: 1.5px solid var(--cizgi); border-radius: 15px 15px 0 0; }
.oge-no { font-size: 12.5px; font-weight: 700; color: var(--soluk); white-space: nowrap; }
.oge-no b { display: inline-grid; place-items: center; min-width: 24px; height: 24px; padding: 0 6px; margin-left: 4px; border-radius: 8px; background: var(--ana); color: #fff; font-size: 12px; }
.oge-ozet { flex: 1; min-width: 0; font-weight: 700; color: var(--murekkep); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.oge-btn { display: flex; gap: 4px; }
.oge-btn button { width: 34px; height: 34px; border-radius: 9px; color: var(--soluk); transition: background .2s, color .2s; }
.oge-btn button:hover { background: #fff; color: var(--murekkep); box-shadow: inset 0 0 0 1px var(--cizgi); }
.oge-btn .sil:hover { color: var(--kirmizi); }
.oge:first-child [data-yukari], .oge:last-child [data-asagi] { opacity: .3; pointer-events: none; }
.oge .alanlar { padding: 18px; }
.liste-bos { padding: 26px; text-align: center; border: 1.5px dashed var(--cizgi); border-radius: 16px; color: var(--soluk); font-weight: 600; }
.liste:not(:empty) + .liste-bos { display: none; }
.ekle-btn { width: 100%; margin-top: 14px; padding: 14px; border-radius: 14px; border: 1.5px dashed color-mix(in srgb, var(--ana) 50%, var(--cizgi)); color: var(--ana); font-weight: 700; background: color-mix(in srgb, var(--ana) 5%, #fff); transition: background .2s; }
.ekle-btn:hover { background: color-mix(in srgb, var(--ana) 11%, #fff); }

.menu-satir { display: grid; grid-template-columns: 200px 1fr auto; align-items: center; gap: 18px; padding: 12px 0; border-bottom: 1px solid var(--cizgi); }
.menu-satir:last-child { border-bottom: 0; }
.menu-ad { display: flex; align-items: center; gap: 12px; font-weight: 700; color: var(--murekkep); }
.menu-ad i { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 10px; background: color-mix(in srgb, var(--ana) 12%, #fff); color: var(--ana); }
.menu-ad small { display: block; font-weight: 600; color: var(--soluk); font-size: 12px; }
.menu-satir .alan input { height: 42px; }

.bilgi-satir { display: flex; flex-wrap: wrap; gap: 10px 26px; font-size: 13.5px; }
.bilgi-satir span { display: inline-flex; gap: 8px; align-items: center; }
.bilgi-satir .iyi { color: #1f8a4c; }
.bilgi-satir .kotu { color: var(--kirmizi); }

.bildirim {
    position: fixed; z-index: 90; left: 50%; bottom: 28px; transform: translate(-50%, 20px);
    display: flex; align-items: center; gap: 10px; padding: 14px 22px; border-radius: 14px;
    background: var(--koyu); color: #fff; font-weight: 700; box-shadow: 0 20px 40px -14px rgba(0, 0, 0, .45);
    opacity: 0; transition: opacity .3s, transform .3s;
}
.bildirim.goster { opacity: 1; transform: translate(-50%, 0); }
.bildirim i { color: #6ee7a1; font-size: 18px; }

.mobil-kaydet { display: none; }

/* İkon seçici penceresi */
.ikon-pencere { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 20px; background: rgba(16, 37, 26, .5); }
.ikon-kutu-pencere { width: min(560px, 100%); max-height: 86vh; overflow-y: auto; background: #fff; border-radius: 22px; padding: 24px; box-shadow: 0 30px 70px -20px rgba(0, 0, 0, .5); }
.ikon-pencere-bas { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.ikon-pencere-bas h3 { font-size: 1.1rem; }
.ikon-pencere-bas button { width: 38px; height: 38px; border-radius: 10px; font-size: 18px; }
.ikon-pencere-bas button:hover { background: var(--zemin); }
.ikon-izgara { display: grid; grid-template-columns: repeat(auto-fill, minmax(62px, 1fr)); gap: 8px; }
.ikon-izgara button { aspect-ratio: 1; border-radius: 14px; font-size: 21px; color: var(--murekkep); background: #f6f8f5; transition: background .15s, color .15s; }
.ikon-izgara button:hover, .ikon-izgara button.secili { background: var(--ana); color: #fff; }
.ikon-not { margin-top: 16px; font-size: 13px; color: var(--soluk); }
.ikon-not a { color: var(--ana); font-weight: 700; }

@media (max-width: 960px) {
    .panel { grid-template-columns: minmax(0, 1fr); }
    .yan { position: sticky; top: 0; z-index: 30; height: auto; flex-direction: row; align-items: center; gap: 10px; padding: 10px 12px; overflow: visible; }
    .yan-logo { padding: 0; margin: 0; border: 0; }
    .yan-logo div { display: none; }
    .yan-logo .logo-ikon { width: 38px; height: 38px; flex-basis: 38px; }
    .yan-menu { flex-direction: row; overflow-x: auto; flex: 1; min-width: 0; gap: 4px; scrollbar-width: none; }
    .yan-menu::-webkit-scrollbar { display: none; }
    .yan-menu a { flex-shrink: 0; padding: 9px 12px; font-size: 13.5px; }
    .yan-menu a i { display: none; }
    .yan-alt { margin: 0; padding: 0; border: 0; display: flex; }
    .yan-alt a span { display: none; }
    .yan-alt a { padding: 10px; }
    .icerik { padding: 0 16px 110px; }
    .ust-bar { position: static; margin: 0 -16px 20px; padding: 18px 16px; background: transparent; }
    .ust-eylem { display: none; }
    .mobil-kaydet {
        display: flex; gap: 10px; position: fixed; z-index: 40; left: 0; right: 0; bottom: 0;
        padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
        background: rgba(255, 255, 255, .95); -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
        border-top: 1px solid var(--cizgi);
    }
    .mobil-kaydet .btn { flex: 1; padding: 13px; }
    .bildirim { bottom: 92px; }
    .kart { padding: 20px 16px 22px; border-radius: 18px; }
    .alanlar { grid-template-columns: 1fr; }
    .oge .alanlar { padding: 14px; }
    .menu-satir { grid-template-columns: 1fr auto; gap: 10px 14px; }
    .menu-satir .alan { grid-column: 1 / -1; grid-row: 2; }
    .alan input[type=text], .alan textarea, .alan select { font-size: 16px; }
}
</style>
    <?php
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Yönetim Paneli - <?= e($SITE_ADI) ?></title>
<script>document.documentElement.className += ' js';</script>
<?php stil($ANA_RENK); ?>
</head>

<?php if (empty($_SESSION['giris'])): ?>
<!-- ================= GİRİŞ EKRANI ================= -->
<body class="giris-sayfa">
    <form class="giris-kart" method="post" action="admin.php">
        <span class="logo-ikon"><i class="fa-solid fa-leaf"></i></span>
        <h1>Yönetim Paneli</h1>
        <p><?= e($SITE_ADI) ?></p>
        <?php if ($girisHata !== ''): ?><div class="giris-hata" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= e($girisHata) ?></div><?php endif; ?>
        <div class="alan">
            <label for="sifre">Şifre</label>
            <div class="sifre-kutu">
                <input type="password" id="sifre" name="sifre" autocomplete="current-password" required autofocus>
                <button type="button" class="sifre-goz" aria-label="Şifreyi göster" onclick="var i=document.getElementById('sifre');i.type=i.type==='password'?'text':'password';this.firstChild.className=i.type==='password'?'fa-regular fa-eye':'fa-regular fa-eye-slash';"><i class="fa-regular fa-eye"></i></button>
            </div>
        </div>
        <button class="btn btn-ana" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Giriş Yap</button>
        <a class="giris-geri" href="index.php"><i class="fa-solid fa-arrow-left"></i> Siteye dön</a>
    </form>
</body>
</html>
<?php exit; endif; ?>

<!-- ================= YÖNETİM PANELİ ================= -->
<body>
<div class="panel">
    <aside class="yan">
        <div class="yan-logo">
            <span class="logo-ikon"><i class="fa-solid fa-leaf"></i></span>
            <div><strong>Yönetim Paneli</strong><small><?= e($SITE_ADI) ?></small></div>
        </div>
        <nav class="yan-menu" aria-label="Panel bölümleri">
            <?php foreach ($SEKMELER as $anahtar => $s): ?>
                <a href="#<?= $anahtar ?>" data-sekme-link="<?= $anahtar ?>"><i class="fa-solid <?= $s['ikon'] ?>"></i><?= e($s['baslik']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="yan-alt">
            <a href="index.php" target="_blank" rel="noopener" title="Siteyi görüntüle"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>Siteyi Görüntüle</span></a>
            <a href="admin.php?cikis=1&amp;t=<?= e($TOKEN) ?>" title="Çıkış yap"><i class="fa-solid fa-right-from-bracket"></i><span>Çıkış Yap</span></a>
        </div>
    </aside>

    <main class="icerik">
        <header class="ust-bar">
            <div>
                <h1 id="sekmeBaslik">Genel Ayarlar</h1>
                <p id="sekmeAciklama"></p>
            </div>
            <div class="ust-eylem">
                <a class="btn btn-cizgi" href="index.php" target="_blank" rel="noopener"><i class="fa-regular fa-eye"></i> Siteyi Gör</a>
                <button class="btn btn-ana" type="submit" form="panelFormu"><span class="kirli-isaret"></span><i class="fa-solid fa-floppy-disk"></i> Kaydet</button>
            </div>
        </header>

        <?php if ($YONETICI_SIFRE === 'admin123'): ?>
            <div class="uyari sari"><i class="fa-solid fa-triangle-exclamation"></i><div>Güvenlik uyarısı: Varsayılan şifre kullanılıyor. <code>admin.php</code> dosyasını açıp en üstteki <code>$YONETICI_SIFRE = 'admin123';</code> satırındaki şifreyi değiştirin.</div></div>
        <?php endif; ?>
        <?php if ($hataMesaji !== ''): ?>
            <div class="uyari kirmizi" role="alert"><i class="fa-solid fa-circle-exclamation"></i><div><?= e($hataMesaji) ?></div></div>
        <?php elseif (!$yazilabilir): ?>
            <div class="uyari kirmizi"><i class="fa-solid fa-circle-exclamation"></i><div>veriler.txt dosyasına yazma izni yok, kaydettiğiniz değişiklikler saklanamaz. Hosting dosya yöneticisinden dosyanın izinlerini 644 (olmazsa 666) yapın.</div></div>
        <?php endif; ?>
        <?php if ($dosyaBozuk): ?>
            <div class="uyari kirmizi"><i class="fa-solid fa-circle-exclamation"></i><div>veriler.txt dosyası okunamadı (biçimi bozulmuş olabilir). Kaydet'e basarsanız dosya bu formdaki bilgilerle yeniden oluşturulur.</div></div>
        <?php elseif (!$dosyaVar): ?>
            <div class="uyari sari"><i class="fa-solid fa-circle-info"></i><div>veriler.txt dosyası bulunamadı. Formu doldurup Kaydet'e bastığınızda otomatik oluşturulur.</div></div>
        <?php endif; ?>

        <form id="panelFormu" method="post" action="admin.php" autocomplete="off">
            <input type="hidden" name="token" value="<?= e($TOKEN) ?>">
            <input type="hidden" name="kaydet" value="1">
            <input type="hidden" name="sekme" id="sekmeAlani" value="genel">

            <?php foreach ($SEKMELER as $sekme => $s): $deger = isset($V[$sekme]) && is_array($V[$sekme]) ? $V[$sekme] : array(); ?>
            <section class="sekme" data-sekme="<?= $sekme ?>" data-baslik="<?= e($s['baslik']) ?>" data-aciklama="<?= e($s['aciklama']) ?>">
                <h2 class="sekme-baslik"><?= e($s['baslik']) ?></h2>

                <?php if ($sekme === 'menu'): ?>
                    <div class="kart">
                        <div class="kart-bas">
                            <h2>Menü Bağlantıları</h2>
                            <p>Yazıları değiştirebilir, anahtarı kapatarak gizleyebilirsiniz. Gizlenen bölüm sitede de gösterilmez (Ana Sayfa ve WhatsApp sadece menüden kalkar).</p>
                        </div>
                        <?php foreach ($MENU as $k => $m):
                            $yazi = isset($deger[$k]['yazi']) ? $deger[$k]['yazi'] : $m[0];
                            $goster = isset($deger[$k]['goster']) ? $deger[$k]['goster'] : true; ?>
                            <div class="menu-satir">
                                <span class="menu-ad"><i class="<?= $k === 'whatsapp' ? 'fa-brands' : 'fa-solid' ?> <?= $m[1] ?>"></i><span><?= e($m[0]) ?><small><?= $k === 'whatsapp' ? 'wa.me bağlantısı' : '#' . $k ?></small></span></span>
                                <div class="alan"><input type="text" name="menu[<?= $k ?>][yazi]" value="<?= e($yazi) ?>" aria-label="<?= e($m[0]) ?> menü yazısı"></div>
                                <label class="anahtar" title="Menüde göster"><input type="checkbox" name="menu[<?= $k ?>][goster]" value="1"<?= $goster ? ' checked' : '' ?>><span class="kaydirac" aria-hidden="true"></span><span>Göster</span></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($s['kartlar'] as $kart): ?>
                    <div class="kart">
                        <div class="kart-bas">
                            <h2><?= e($kart['baslik']) ?></h2>
                            <?php if (!empty($kart['aciklama'])): ?><p><?= e($kart['aciklama']) ?></p><?php endif; ?>
                        </div>
                        <?php if (isset($kart['alanlar'])): ?>
                            <div class="alanlar">
                                <?php foreach ($kart['alanlar'] as $alan):
                                    if (array_key_exists($alan['ad'], $deger)) {
                                        $d = $deger[$alan['ad']];
                                    } else {
                                        $d = $alan['tip'] === 'onay' ? true : $alan['varsayilan'];
                                    }
                                    echo alan_html($sekme . '[' . $alan['ad'] . ']', $alan, is_scalar($d) ? $d : '');
                                endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($kart['liste'])): $liste = $kart['liste']; $ogeler = isset($deger[$liste['ad']]) && is_array($deger[$liste['ad']]) ? array_values($deger[$liste['ad']]) : array(); ?>
                            <div class="liste" data-liste="<?= $sekme . '_' . $liste['ad'] ?>"><?php
                                foreach ($ogeler as $i => $oge) {
                                    echo oge_html($sekme, $liste, $i, $oge, $i + 1);
                                }
                            ?></div>
                            <div class="liste-bos">Bu listede henüz öğe yok.</div>
                            <button type="button" class="ekle-btn" data-ekle="<?= $sekme . '_' . $liste['ad'] ?>"><i class="fa-solid fa-plus"></i> Yeni <?= e($liste['etiket']) ?> Ekle</button>
                            <template id="sablon-<?= $sekme . '_' . $liste['ad'] ?>"><?= oge_html($sekme, $liste, '__K__', null, '') ?></template>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($sekme === 'genel'): ?>
                    <div class="kart">
                        <div class="kart-bas"><h2>Veri Dosyası</h2></div>
                        <div class="bilgi-satir">
                            <span><i class="fa-regular fa-file-lines"></i> veriler.txt</span>
                            <?php if ($dosyaVar): ?>
                                <span><i class="fa-regular fa-clock"></i> Son kayıt: <?= date('d.m.Y H:i', filemtime($VERI_DOSYASI)) ?></span>
                            <?php endif; ?>
                            <?php if ($yazilabilir): ?>
                                <span class="iyi"><i class="fa-solid fa-circle-check"></i> Yazılabilir</span>
                            <?php else: ?>
                                <span class="kotu"><i class="fa-solid fa-circle-xmark"></i> Yazma izni yok</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
            <?php endforeach; ?>
        </form>
    </main>
</div>

<div class="mobil-kaydet">
    <a class="btn btn-cizgi" href="index.php" target="_blank" rel="noopener"><i class="fa-regular fa-eye"></i> Site</a>
    <button class="btn btn-ana" type="submit" form="panelFormu"><span class="kirli-isaret"></span><i class="fa-solid fa-floppy-disk"></i> Kaydet</button>
</div>

<div class="bildirim" id="bildirim" role="status" aria-live="polite"><i class="fa-solid fa-circle-check"></i><span>Değişiklikler kaydedildi.</span></div>

<div class="ikon-pencere" id="ikonPencere" hidden>
    <div class="ikon-kutu-pencere" role="dialog" aria-modal="true" aria-labelledby="ikonPencereBaslik">
        <div class="ikon-pencere-bas">
            <h3 id="ikonPencereBaslik">İkon Seçin</h3>
            <button type="button" data-ikon-kapat aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="ikon-izgara">
            <?php foreach ($IKONLAR as $ik): ?>
                <button type="button" data-ikon="fa-solid <?= $ik ?>" title="<?= $ik ?>"><i class="fa-solid <?= $ik ?>"></i></button>
            <?php endforeach; ?>
        </div>
        <p class="ikon-not">Daha fazla ikon için <a href="https://fontawesome.com/search?o=r&amp;m=free" target="_blank" rel="noopener">fontawesome.com</a> adresinden bir ikon seçip adını (örn. <code>fa-solid fa-heart</code>) kutuya yazabilirsiniz.</p>
    </div>
</div>

<script>
(function () {
    'use strict';
    var d = document, w = window;
    var form = d.getElementById('panelFormu');
    var yeniKaydedildi = /[?&]kaydedildi=1/.test(location.search); // sekme ayarı adresi değiştirmeden önce oku
    function hepsi(secici, kok) { return Array.prototype.slice.call((kok || d).querySelectorAll(secici)); }

    /* ---------- Sekmeler ---------- */
    var sekmeler = hepsi('.sekme');
    var sekmeLinkleri = hepsi('[data-sekme-link]');
    function sekmeAc(ad, yukari) {
        var hedef = d.querySelector('.sekme[data-sekme="' + ad + '"]') || sekmeler[0];
        ad = hedef.getAttribute('data-sekme');
        sekmeler.forEach(function (s) { s.classList.toggle('aktif', s === hedef); });
        sekmeLinkleri.forEach(function (a) {
            var aktif = a.getAttribute('data-sekme-link') === ad;
            a.classList.toggle('aktif', aktif);
            if (aktif && a.scrollIntoView && w.innerWidth <= 960) a.scrollIntoView({ block: 'nearest', inline: 'center' });
        });
        d.getElementById('sekmeBaslik').textContent = hedef.getAttribute('data-baslik');
        d.getElementById('sekmeAciklama').textContent = hedef.getAttribute('data-aciklama');
        d.getElementById('sekmeAlani').value = ad;
        if (history.replaceState) history.replaceState(null, '', location.pathname + '#' + ad);
        if (yukari) w.scrollTo(0, 0);
    }
    sekmeLinkleri.forEach(function (a) {
        a.addEventListener('click', function (e) { e.preventDefault(); sekmeAc(a.getAttribute('data-sekme-link'), true); });
    });
    sekmeAc(location.hash.replace('#', '') || 'genel', false);
    w.addEventListener('hashchange', function () { sekmeAc(location.hash.replace('#', ''), true); });

    /* ---------- Kaydedilmemiş değişiklik uyarısı ---------- */
    var kirli = false;
    function kirlet() { kirli = true; d.body.classList.add('kirli'); }
    form.addEventListener('input', kirlet);
    form.addEventListener('change', kirlet);
    form.addEventListener('submit', function () { kirli = false; });
    w.addEventListener('beforeunload', function (e) {
        if (kirli) { e.preventDefault(); e.returnValue = ''; }
    });
    // Ctrl+S ile kaydet
    d.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            kirli = false;
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        }
    });

    /* ---------- Kaydedildi bildirimi ---------- */
    if (yeniKaydedildi) {
        var bildirim = d.getElementById('bildirim');
        setTimeout(function () { bildirim.classList.add('goster'); }, 100);
        setTimeout(function () { bildirim.classList.remove('goster'); }, 3600);
        if (history.replaceState) history.replaceState(null, '', location.pathname + location.hash);
    }

    /* ---------- Görsel önizleme ---------- */
    function youtubeId(adres) {
        var m = String(adres).match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/))([A-Za-z0-9_-]{11})/);
        if (m) return m[1];
        return /^[A-Za-z0-9_-]{11}$/.test(String(adres).trim()) ? String(adres).trim() : '';
    }
    function onizle(kutu) {
        var input = kutu.querySelector('input'), img = kutu.querySelector('img');
        var adres = input.value.trim();
        var oge = kutu.closest('.oge');
        var tur = oge ? oge.querySelector('select[data-tur]') : null;
        if (tur && tur.value === 'video') {
            var id = youtubeId(adres);
            adres = id ? 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg' : '';
            input.placeholder = 'https://www.youtube.com/watch?v=...';
        } else if (tur) {
            input.placeholder = 'https://...';
        }
        kutu.classList.remove('hata');
        if (!adres) { kutu.classList.add('bos'); img.removeAttribute('src'); img.removeAttribute('data-asil'); return; }
        kutu.classList.remove('bos');
        var hedef = kucukOnizleme(adres);
        img.setAttribute('data-asil', adres);
        if (img.getAttribute('src') !== hedef) img.setAttribute('src', hedef);
    }
    // Önizlemeler de küçültülmüş haliyle yüklenir; büyük fotoğraflar paneli yavaşlatmaz
    var hizlandirKutusu = form.querySelector('[name="genel[gorsel_hizlandir]"]');
    function kucukOnizleme(adres) {
        if (!hizlandirKutusu || !hizlandirKutusu.checked || !/^https?:\/\//i.test(adres) || /^https?:\/\/(localhost|127\.|10\.|192\.168\.|i\.ytimg\.com)/i.test(adres) || /\.(svg|gif)(\?|#|$)/i.test(adres)) return adres;
        return 'https://wsrv.nl/?url=' + encodeURIComponent(adres) + '&w=160&h=120&fit=cover&output=webp&q=70';
    }
    hepsi('.resim-kutu').forEach(onizle);
    form.addEventListener('error', function (e) {
        var img = e.target;
        if (img.tagName !== 'IMG') return;
        var asil = img.getAttribute('data-asil');
        if (asil && img.getAttribute('src') !== asil) { img.setAttribute('src', asil); return; } // küçük hali alınamadı, orijinali dene
        var k = img.closest('.resim-kutu');
        if (k) k.classList.add('hata');
    }, true);
    var onizlemeZaman;
    form.addEventListener('input', function (e) {
        var kutu = e.target.closest('.resim-kutu');
        if (kutu) { clearTimeout(onizlemeZaman); onizlemeZaman = setTimeout(function () { onizle(kutu); }, 350); }
        if (e.target.hasAttribute('data-ozet')) {
            var oge = e.target.closest('.oge');
            if (oge) oge.querySelector('.oge-ozet').textContent = e.target.value;
        }
        if (e.target.closest('.ikon-kutu')) ikonGuncelle(e.target);
        if (e.target.type === 'text' && e.target.closest('.renk-kutu') && /^#[0-9a-f]{6}$/i.test(e.target.value)) {
            e.target.parentNode.querySelector('input[type=color]').value = e.target.value.toLowerCase();
        }
        if (e.target.type === 'color') e.target.parentNode.querySelector('input[type=text]').value = e.target.value;
    });
    form.addEventListener('change', function (e) {
        if (e.target.hasAttribute('data-tur')) {
            var kutu = e.target.closest('.oge').querySelector('.resim-kutu');
            if (kutu) onizle(kutu);
        }
    });

    /* ---------- İkonlar ---------- */
    function ikonSinif(deger) {
        deger = deger.replace(/[^a-z0-9 \-]/gi, '').trim();
        if (!deger) return 'fa-solid fa-leaf';
        return /\bfa-(solid|regular|brands)\b/.test(deger) ? deger : 'fa-solid ' + deger;
    }
    function ikonGuncelle(input) {
        input.parentNode.querySelector('.ikon-onizle i').className = ikonSinif(input.value);
    }
    var ikonPencere = d.getElementById('ikonPencere');
    var ikonHedef = null;
    function ikonPencereKapat() { ikonPencere.hidden = true; if (ikonHedef) ikonHedef.focus(); }
    ikonPencere.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (e.target === ikonPencere || (b && b.hasAttribute('data-ikon-kapat'))) { ikonPencereKapat(); return; }
        if (b && b.hasAttribute('data-ikon') && ikonHedef) {
            ikonHedef.value = b.getAttribute('data-ikon');
            ikonGuncelle(ikonHedef);
            kirlet();
            ikonPencereKapat();
        }
    });

    /* ---------- Listeler: ekle / sil / taşı ---------- */
    function numarala(liste) {
        hepsi(':scope > .oge', liste).forEach(function (oge, i) { oge.querySelector('.oge-no b').textContent = i + 1; });
    }
    form.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;
        var oge = b.closest('.oge'), liste;
        if (b.hasAttribute('data-ekle')) {
            var ad = b.getAttribute('data-ekle');
            liste = d.querySelector('.liste[data-liste="' + ad + '"]');
            var anahtar = 'y' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
            var gecici = d.createElement('div');
            gecici.innerHTML = d.getElementById('sablon-' + ad).innerHTML.replace(/__K__/g, anahtar);
            var yeni = gecici.firstElementChild;
            liste.appendChild(yeni);
            numarala(liste);
            hepsi('.resim-kutu', yeni).forEach(onizle);
            yeni.scrollIntoView({ behavior: 'smooth', block: 'center' });
            var ilk = yeni.querySelector('[data-ozet]') || yeni.querySelector('input[type=text], textarea');
            if (ilk) setTimeout(function () { ilk.focus({ preventScroll: true }); }, 350);
            kirlet();
        } else if (b.hasAttribute('data-sil')) {
            if (!confirm('Bu öğeyi silmek istediğinize emin misiniz?')) return;
            liste = oge.parentNode;
            oge.parentNode.removeChild(oge);
            numarala(liste);
            kirlet();
        } else if (b.hasAttribute('data-yukari') && oge.previousElementSibling) {
            oge.parentNode.insertBefore(oge, oge.previousElementSibling);
            numarala(oge.parentNode);
            b.focus();
            kirlet();
        } else if (b.hasAttribute('data-asagi') && oge.nextElementSibling) {
            oge.parentNode.insertBefore(oge.nextElementSibling, oge);
            numarala(oge.parentNode);
            b.focus();
            kirlet();
        } else if (b.hasAttribute('data-ikon-sec')) {
            ikonHedef = b.parentNode.querySelector('input');
            hepsi('[data-ikon]', ikonPencere).forEach(function (x) { x.classList.toggle('secili', x.getAttribute('data-ikon') === ikonSinif(ikonHedef.value)); });
            ikonPencere.hidden = false;
            var secili = ikonPencere.querySelector('.secili') || ikonPencere.querySelector('[data-ikon]');
            secili.focus();
        }
    });
    d.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !ikonPencere.hidden) ikonPencereKapat();
    });
})();
</script>
</body>
</html>
