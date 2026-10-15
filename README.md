# Dyt. Ayşe Kaya — Diyetisyen Web Sitesi

Saf PHP, HTML, CSS ve JavaScript ile yazılmış tek sayfalık diyetisyen web sitesi.
Yönetim paneli (`admin.php`) ile tüm içerikler düzenlenebilir; veriler `veriler.txt` dosyasında saklanır.

## 🌐 Canlı Demo

**https://bionluk-diyetisyen-web-sitesi.vercel.app**

> **Not:** Canlı demo, sitenin salt okunur statik bir dışa aktarımıdır.
> Admin paneli (`admin.php`) statik deploy'da çalışmaz — PHP çalıştırma ortamı gerektirir.
> İçerik değişikliği yapıp yeniden yayınlamak için aşağıdaki adımları izleyin.

## 🚀 Yeniden Yayınlama

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

## 🗄️ Veritabanını Sıfırlama

Bu projede veritabanı yoktur. İçerikler `veriler.txt` dosyasında saklanır.
Sıfırlamak için `veriler.txt` dosyasını orijinal haliyle değiştirin ve yukarıdaki yeniden yayınlama adımlarını uygulayın.

## 📋 Detaylı Kurulum ve Kullanım

Ayrıntılı talimatlar için [TESLIM-NOTLARI.md](TESLIM-NOTLARI.md) dosyasına bakın.
