# Dyt. Ayşe Kaya — Diyetisyen Web Sitesi

Saf PHP, HTML, CSS ve JavaScript ile yazılmış, modern ve tam responsive tek sayfalık diyetisyen web sitesi.
Yönetim paneli (`admin.php`) ile tüm içerikler düzenlenebilir; veriler `veriler.txt` dosyasında saklanır.
Veritabanı, kurulum sihirbazı veya ek program gerekmez.

## 🌐 Canlı Demo

**https://bionluk-diyetisyen-web-sitesi.vercel.app**

> **Not:** Canlı demo, sitenin salt okunur statik bir dışa aktarımıdır.
> Admin paneli (`admin.php`) statik deploy'da çalışmaz — PHP çalıştırma ortamı gerektirir.
> İçerik değişikliği yapıp yeniden yayınlamak için aşağıdaki adımları izleyin.

---

## ✨ Özellikler

- **Yönetim Paneli** — Kod bilgisi olmadan tüm içerikleri düzenleme (13 sekme: Genel Ayarlar, Menü, Hero, Hizmetler, VKİ Hesaplama, İstatistikler, Galeri, Hakkında, Blog, Yorumlar, S.S.S., İletişim, Alt Bilgi, KVKK & Çerezler)
- **Tam Responsive** — 320px telefon ekranından geniş masaüstüne kadar sorunsuz görünüm
- **VKİ Hesaplama** — Ziyaretçinin boy/kilo girerek vücut kitle indeksini hesaplaması, sonucu WhatsApp ile gönderme
- **WhatsApp Entegrasyonu** — İletişim formu mesajları doğrudan WhatsApp'a yönlenir, ayrı e-posta ayarı gerekmez
- **Çerez Yönetimi (KVKK)** — Onay verilmeden Google Haritalar ve YouTube yüklenmez; tercih 12 ay hatırlanır
- **Fotoğraf Hızlandırma** — wsrv.nl servisi ile görseller WebP'ye dönüştürülüp boyutlandırılır (~5,6 MB → ~230 KB)
- **Blog** — "Devamını Oku" ile açılan tam metin desteği
- **Mobil Arama Butonu** — Telefon/tablette sabit arama butonu
- **Animasyonlu İstatistikler** — 0'dan sayarak artan sayılar
- **Hero Slider** — Kayan slaytlar ve üzerine bilgi kartları
- **SEO Hazır** — Open Graph meta etiketleri, açıklayıcı başlıklar

---

## 📁 Dosya Yapısı

| Dosya | Açıklama |
|---|---|
| `index.php` | Ziyaretçilerin gördüğü ana sayfa (2770 satır) |
| `admin.php` | Yönetim paneli — tüm içerikler buradan düzenlenir |
| `veriler.txt` | JSON formatında içerik deposu |
| `vercel.json` | Vercel deploy ayarları |
| `.vercelignore` | Vercel'e yüklenmeyecek dosyalar |
| `_static_output/` | PHP'den render edilmiş statik HTML |

---

## 🛠️ Yerel Geliştirme

### Gereksinimler

- PHP 7.0 veya üzeri (7.4 ya da 8.x önerilir)

### Çalıştırma

```powershell
C:\wamp64\bin\php\php8.4.0\php.exe -S localhost:8080 -t "d:\Talha\PROJELER\bionluk-diyetisyen-web-sitesi"
```

| Adres | Sayfa |
|---|---|
| http://localhost:8080 | Site |
| http://localhost:8080/admin.php | Yönetim Paneli |

Durdurmak için terminalde **Ctrl + C** basın.

---

## 🚀 Vercel'e Yeniden Yayınlama

Yerel ortamda içerik değişikliği yaptıktan sonra:

```powershell
# 1. PHP ile statik HTML oluştur
$phpExe = "C:\wamp64\bin\php\php8.4.0\php.exe"
& $phpExe -r "
  chdir('d:/Talha/PROJELER/bionluk-diyetisyen-web-sitesi');
  `$_SERVER['REQUEST_METHOD']='GET'; `$_SERVER['REQUEST_URI']='/';
  `$_SERVER['HTTP_HOST']='localhost'; `$_SERVER['HTTPS']='on';
  `$_SERVER['SERVER_PORT']='443'; `$_SERVER['SCRIPT_NAME']='/index.php';
  ob_start(); include 'index.php'; `$h=ob_get_clean();
  file_put_contents('_static_output/index.html', `$h);
  echo 'OK: '.strlen(`$h).' bytes';
"

# 2. Vercel'e deploy et
npx vercel deploy --prod --yes
```

---

## 🗄️ İçeriği Sıfırlama

Bu projede veritabanı yoktur. İçerikler `veriler.txt` dosyasında saklanır.
Sıfırlamak için `veriler.txt` dosyasını orijinal haliyle değiştirin ve yukarıdaki yeniden yayınlama adımlarını uygulayın.

---

## 🔒 Güvenlik

- Panel şifresini `admin.php` dosyasının en üst satırından değiştirin (`$YONETICI_SIFRE`)
- `veriler.txt` içinde şifre saklanmaz, yalnızca ziyaretçiye açık içerikler bulunur
- Statik deploy'da PHP dosyaları ve `veriler.txt` Vercel'e yüklenmez (`.vercelignore` ile engellenir)

---

## 🧰 Teknoloji

- **Diller:** PHP, HTML, CSS, JavaScript — çatı (framework) veya derleme adımı yok
- **Dış Kaynaklar:** Google Fonts, Font Awesome (cdnjs), wsrv.nl (fotoğraf CDN), YouTube, Google Haritalar
- **Hosting:** Vercel Hobby (ücretsiz) — statik HTML olarak sunulur
- **Test:** PHP 7.4 ve 8.1, 320–390px mobil, tablet ve masaüstü

---

## 📋 Detaylı Kurulum ve Kullanım

Ayrıntılı talimatlar, panel kullanımı ve sık karşılaşılan durumlar için [TESLIM-NOTLARI.md](TESLIM-NOTLARI.md) dosyasına bakın.
