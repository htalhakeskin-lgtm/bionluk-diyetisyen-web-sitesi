Siteyi Çalıştırmak
PowerShell veya Terminal aç ve şu komutu yaz:

powershell
C:\wamp64\bin\php\php8.4.0\php.exe -S localhost:8080 -t "d:\Talha\PROJELER\bionluk-diyetisyen-web-sitesi"
Sonra tarayıcıda:

Site: http://localhost:8080
Panel: http://localhost:8080/admin.php
🛑 Siteyi Durdurmak
Terminal penceresinde Ctrl + C bas. Sunucu kapanır.

💡 İpuçları
Durum	Açıklama
Port meşgulse	8080 yerine başka port yaz, örn: -S localhost:3000
Proje klasöründeysen	-t parametresini atlayabilirsin: C:\wamp64\bin\php\php8.4.0\php.exe -S localhost:8080
Farklı PHP sürümü	php8.4.0 yerine php8.1.31, php7.4.33 vb. yazabilirsin

# Dyt. Ayşe Kaya Web Sitesi — Teslim Notları

> ⚠️ **Bu dosyayı hostinge yüklemeyin.** İçinde yönetim panelinin varsayılan şifresi yazıyor. Hostinge yalnızca aşağıdaki 3 dosya yüklenecek.

---

## 1. Paketin İçeriği

| Dosya | Ne işe yarar? |
|---|---|
| `index.php` | Ziyaretçilerin gördüğü site (ana sayfa). |
| `admin.php` | Sitedeki tüm yazıları, fotoğrafları ve ayarları değiştirdiğiniz yönetim paneli. |
| `veriler.txt` | Sitedeki tüm içerik bu dosyada saklanır. Panelde **Kaydet**'e bastığınızda bu dosya güncellenir. |

Site saf PHP, HTML, CSS ve JavaScript ile yazıldı. Veritabanı, kurulum sihirbazı veya ek program gerekmez.

---

## 2. Kurulum (5 dakika)

1. **Hosting gereksinimi:** PHP 7.0 veya üzeri (7.4 ya da 8.x önerilir). Site PHP 7.4 ve 8.1 ile test edildi. Neredeyse tüm Türk hosting firmalarında varsayılan olarak bu sürümler vardır.
2. **Dosyaları yükleyin:** Hosting panelinizdeki (cPanel, Plesk vb.) **Dosya Yöneticisi**'ni ya da FTP'yi açın. Üç dosyayı **aynı klasöre**, genellikle `public_html` klasörüne yükleyin.
   - Kodları kopyala-yapıştır ile oluşturacaksanız dosyaları **UTF-8** kodlamasıyla kaydedin. cPanel düzenleyicisi bunu kendisi yapar. Not Defteri kullanıyorsanız "Farklı Kaydet" ekranında Kodlama: **UTF-8** seçin.
3. **Yazma izni verin:** Dosya Yöneticisi'nde `veriler.txt` dosyasına sağ tıklayın → **İzinler (Permissions)** → **644** yapın. Panelde kaydederken "yazma izni yok" uyarısı alırsanız **666** yapın.
4. **Şifrenizi değiştirin (zorunlu):** `admin.php` dosyasını düzenle modunda açın. En üstteki şu satırı bulun:
   ```php
   $YONETICI_SIFRE = 'admin123';
   ```
   `admin123` yerine kendi şifrenizi yazıp kaydedin. Tırnak işaretlerini silmeyin. Şifre değiştirilene kadar panelde sarı bir güvenlik uyarısı görünür.
5. **Kontrol edin:**
   - Site: `https://siteadresiniz.com`
   - Panel: `https://siteadresiniz.com/admin.php`

---

## 3. Yayına Almadan Önce Yapılacaklar

Sitede şu an referans siteden alınmış **örnek içerikler** var. Yayına almadan önce bunları kendi bilgilerinizle değiştirin:

- [ ] Panel şifresini değiştirdim (`admin.php` en üst satır).
- [ ] **İletişim** sekmesi: telefon (şu an örnek: +90 312 123 45 67), e-posta, adres, çalışma saatleri, Google Haritalar linki.
- [ ] **Genel Ayarlar** sekmesi: WhatsApp numarası ve hazır mesaj.
- [ ] Fotoğraflar: hero, Hakkında ve galerideki fotoğraflar şu an referans siteden (stucv.com) geliyor. O dosyalar silinirse sitede görünmez olurlar; kendi fotoğraflarınızla değiştirin (bkz. Bölüm 5).
- [ ] Galerideki **YouTube videoları** referans siteden alınmış örneklerdir ve başka bir diyetisyene aittir. Kendi videolarınızla değiştirin.
- [ ] **Yorumlar** sekmesi: örnek yorumlardır. Kendi danışanlarınızın, izin aldığınız yorumlarıyla değiştirin.
- [ ] **İstatistikler** sekmesi: danışan sayısı, deneyim yılı gibi rakamlar.
- [ ] **Alt Bilgi** sekmesi: sosyal medya linkleri (şu an genel instagram.com / linkedin.com adresleri) ve telif yazısı.
- [ ] **KVKK & Çerezler** sekmesi: metinleri bir hukukçuya kontrol ettirin (bkz. Bölüm 9).

---

## 4. Yönetim Panelinin Kullanımı

Paneldeki her sekme sitenin bir bölümüne karşılık gelir:

| Sekme | Neyi değiştirir? |
|---|---|
| Genel Ayarlar | Site adı, logo ikonu, sitenin ana rengi, Google başlığı/açıklaması, WhatsApp numarası, fotoğraf hızlandırma |
| Menü | Üst menüdeki yazılar; bir bölümü menüden ve siteden gizleme |
| Hero (Karşılama) | Sayfanın en üstündeki kayan slaytlar ve görselin üzerindeki küçük kartlar |
| Hizmetler | Hizmet kartları |
| VKİ Hesaplama | Vücut kitle indeksi hesaplama bölümünün yazıları; bölümü açma/kapatma |
| İstatistikler | Koyu yeşil şerit üzerindeki sayılar |
| Galeri | Fotoğraflar ve YouTube videoları |
| Hakkında | Tanıtım yazısı, fotoğraf, öne çıkan maddeler, fotoğraf üstündeki rozet |
| Blog | Blog yazıları ("Devamını Oku" ile açılan tam metin) |
| Yorumlar | Danışan yorumları |
| S.S.S. | Sık sorulan sorular |
| İletişim | Telefon, e-posta, adres, çalışma saatleri, harita, mobil arama butonu |
| Alt Bilgi | Sayfanın en altındaki yazı, telif yazısı, sosyal medya hesapları |
| KVKK & Çerezler | Çerez bandı, form onay kutusu, KVKK Aydınlatma Metni, Çerez Politikası |

**Bilmeniz gerekenler:**

- Değişikliklerin siteye geçmesi için sağ üstteki **Kaydet** butonuna basın. Klavyeden **Ctrl+S** ile de kaydedebilirsiniz. Kayıttan sonra "Değişiklikler kaydedildi." bildirimi çıkar.
- Kaydetmeden sayfadan çıkmaya çalışırsanız tarayıcı sizi uyarır.
- Listelerde (slayt, hizmet, galeri, blog vb.):
  - **+ Yeni ... Ekle** ile öğe eklenir.
  - **↑ / ↓** ile sıra değiştirilir.
  - **çöp kutusu** ile öğe silinir.
  - Tamamen boş bırakılan öğeler kaydedilmez. Bir listede hiç öğe kalmazsa o bölüm sitede otomatik gizlenir.
- **İkonlar:** İkon alanındaki **Seç** butonuyla hazır ikonlardan birini seçin.
- **Başlıkta vurgu:** Bir kelimeyi yıldız içine yazarsanız yeşil italik görünür. Örnek: `Danışan *Yorumları*`. Hero başlıklarında son kelime kendiliğinden vurgulanır.
- **Uzun yazılar (blog, Hakkında):** Paragraflar arasında bir boş satır bırakın. Kalın yazmak için `**böyle**` yazın.
- **Çalışma saatleri:** Her satıra bir gün/saat yazın.
- **İstatistik sayıları:** Sadece rakam yazarsanız (ör. `1200`) ekranda 0'dan sayarak artar. `7/24` gibi yazılar da kullanılabilir, onlar sabit görünür.
- **Menüden gizleme:** Menü sekmesinde bir bölümün anahtarını kapatırsanız o bölüm hem menüden hem siteden gizlenir. Ana Sayfa ve WhatsApp yalnızca menüden kalkar.

---

## 5. Fotoğraf ve Video Ekleme

Panel fotoğrafı **adresiyle** ister:

1. Fotoğrafı hosting Dosya Yöneticisi'nden, örneğin `public_html/resimler/` klasörüne yükleyin.
2. Panele adresini yazın. Tam adres (`https://siteadresiniz.com/resimler/ofis.jpg`) ya da kısa hali (`resimler/ofis.jpg`) olabilir.
3. Adres doğruysa alanın yanında küçük önizleme görünür. **Kırmızı uyarı işareti** görünüyorsa adres hatalıdır.

**Önerilen fotoğraf boyutu:** Genişliği 1200–1600 piksel olan **JPG** veya **WebP** dosyaları kullanın. 1 MB'ı aşan PNG fotoğraflar siteyi yavaşlatır.

**Video eklemek için:** Galeri sekmesinde Tür olarak **YouTube Videosu**'nu seçin ve videonun linkini yapıştırın. `youtube.com/watch?v=...`, `youtu.be/...` ve `youtube.com/shorts/...` linklerinin hepsi çalışır. Kapak resmi otomatik alınır.

### Fotoğraf hızlandırma

**Genel Ayarlar → Hız → "Fotoğrafları hızlandır"** açıkken fotoğraflar, ücretsiz **wsrv.nl** görsel servisi üzerinden ekran boyutuna göre küçültülüp hızlı yüklenen WebP biçiminde gösterilir.

- Mevcut örnek fotoğraflarda sayfadaki fotoğraf yükü **yaklaşık 5,6 MB'tan 230 KB'a** düştü.
- Orijinal dosyalarınız değişmez. Servise ulaşılamazsa site otomatik olarak orijinal fotoğrafı gösterir.
- Bu özellik yalnızca internette yayında olan sitede çalışır. Kendi bilgisayarınızda (localhost) denerken fotoğraflar orijinal halleriyle yüklenir; bu normaldir.
- Bir sorun yaşarsanız anahtarı kapatmanız yeterlidir.

---

## 6. WhatsApp, İletişim Formu ve Arama Butonu

- **WhatsApp numarası:** Genel Ayarlar sekmesinde. `905321234567`, `0532 123 45 67` veya `+90 532 123 45 67` biçimlerinin hepsi doğru çalışır.
- **İletişim formu e-posta göndermez:** Ziyaretçi formu doldurup **Gönder**'e bastığında, yazdıkları hazır bir mesaj olarak **WhatsApp'ta açılır** ve ziyaretçi göndererek size ulaşır. Böylece hostinginizde e-posta ayarı gerekmez ve mesajlar spama düşmez. WhatsApp numarası boş bırakılırsa form ziyaretçinin e-posta uygulamasını açar.
- **Mobil arama butonu:** Telefon ve tablette:
  - üst menüde hamburgerin yanında bir arama ikonu,
  - sağ altta WhatsApp butonunun üstünde sabit bir **Ara** butonu
  
  görünür. İkisi de İletişim sekmesindeki telefon numarasını arar. Sabit butonlar, sayfanın en üstündeki butonlarla çakışmasın diye sayfa biraz aşağı kaydırılınca belirir. İletişim sekmesindeki anahtarla kapatılabilir.

---

## 7. VKİ Hesaplama

Ziyaretçi boy ve kilosunu girer; vücut kitle indeksini, sınıfını (Dünya Sağlık Örgütü yetişkin sınıflandırması), boyuna göre sağlıklı kilo aralığını ve kaç kilo vermesi/alması gerektiğini görür. **Sonucumu Diyetisyene Gönder** butonu sonucu WhatsApp ile size iletir.

- Hesaplama ziyaretçinin tarayıcısında yapılır; boy ve kilo bilgisi sunucuya gönderilmez.
- Bölüm, menünün referans siteyle aynı kalması istendiği için **menüde yer almaz**. Ziyaretçiyi bu bölüme yönlendirmek isterseniz bir hero slaytının buton linkine `#vki` yazabilirsiniz.

---

## 8. Paylaşılabilir Linkler

Bu adresler sayfayı açıp ilgili bölümü veya pencereyi doğrudan gösterir. Instagram biyografisinde veya WhatsApp mesajında kullanabilirsiniz:

| Link | Açılan yer |
|---|---|
| `siteadresiniz.com/#vki` | VKİ hesaplama bölümü |
| `siteadresiniz.com/#yazi-1` | 1. blog yazısı (`#yazi-2`, `#yazi-3` …) |
| `siteadresiniz.com/#iletisim` | İletişim bölümü |
| `siteadresiniz.com/#kvkk-aydinlatma-metni` | KVKK Aydınlatma Metni |
| `siteadresiniz.com/#cerez-politikasi` | Çerez Politikası |

---

## 9. KVKK ve Çerezler

**Çerez bandı:** Ziyaretçi siteye ilk girdiğinde **Reddet / Kabul Et** seçenekleriyle bir bant çıkar.

- Onay verilmeden **Google Haritalar** ve **YouTube videoları** yüklenmez; yerlerinde "Haritayı Göster" ve "Kabul Et ve Oynat" seçenekleri görünür.
- Seçim 12 ay hatırlanır. Ziyaretçi sayfanın en altındaki **Çerez Tercihleri** bağlantısıyla seçimini istediği zaman değiştirebilir.
- Site kendisi ziyaretçilere reklam veya takip çerezi bırakmaz.

**KVKK Aydınlatma Metni ve Çerez Politikası:** Sayfanın en altındaki bağlantılardan açılır. İletişim formunda ziyaretçinin işaretlemesi zorunlu bir "Aydınlatma Metni'ni okudum" kutusu vardır.

- Metinlerde `{site_adi}`, `{adres}`, `{telefon}` ve `{eposta}` yazan yerlere sitedeki güncel bilgiler otomatik gelir. İletişim bilgilerinizi değiştirdiğinizde metinler de kendiliğinden güncellenir.
- ⚖️ **Önemli:** Metinler genel bir şablondur. Yayına almadan önce kendi çalışma şeklinize göre bir hukukçuya kontrol ettirmeniz önerilir.

---

## 10. Güvenlik ve Yedekleme

- Panel şifrenizi kimseyle paylaşmayın. Unutursanız `admin.php` dosyasının en üstündeki satıra yeni bir şifre yazmanız yeterlidir.
- **Yedek alın:** Her kayıt `veriler.txt` dosyasının üzerine yazar ve geri alma özelliği yoktur. Büyük değişikliklerden önce Dosya Yöneticisi'nden `veriler.txt` dosyasını bilgisayarınıza indirin. Bir sorun olursa bu dosyayı geri yüklemeniz yeterlidir.
- `veriler.txt` dosyasını elle düzenlemek yerine paneli kullanın. Elle düzenlemede biçim bozulursa site uyarı gösterir; yedeği geri yükleyerek düzeltebilirsiniz.
- `veriler.txt` dosyası internetten okunabilir. İçinde yalnızca sitede zaten herkese açık görünen bilgiler vardır; şifre bu dosyada **saklanmaz**. Panele de gizli bilgi yazmayın.

---

## 11. Sık Karşılaşılan Durumlar

| Durum | Çözüm |
|---|---|
| Panelde "Kaydedilemedi / yazma izni yok" uyarısı | `veriler.txt` izinlerini 644, olmazsa 666 yapın (Bölüm 2, adım 3). |
| Sitenin en üstünde sarı "veriler.txt bulunamadı" yazısı | `veriler.txt` yüklenmemiş veya `index.php` ile aynı klasörde değil. |
| Panelde "veriler.txt okunamadı" uyarısı | Dosya elle düzenlenip bozulmuş. Yedeği geri yükleyin. |
| Fotoğraf görünmüyor | Adresi tarayıcıda açıp kontrol edin; panelde alanın yanında kırmızı uyarı işareti çıkıyorsa adres hatalıdır. |
| Harita görünmüyor | Çerez onayı verilmemiştir; "Haritayı Göster"e basın. İletişim sekmesinde adres boşsa veya "haritayı göster" kapalıysa harita çıkmaz. |
| Video oynamıyor | Çerez onayı verilmemiştir; videoda "Kabul Et ve Oynat"a basın. |
| Kaydettim ama sitede eski hali görünüyor | Sayfayı yenileyin (bilgisayarda **Ctrl+F5**). |
| Şifreyi unuttum | `admin.php` en üst satıra yeni şifreyi yazın. |

---

## 12. Teknik Özet

- **Diller:** PHP, HTML, CSS, JavaScript. React veya başka bir çatı (framework) kullanılmadı, derleme gerekmez.
- **Veri:** `veriler.txt` içinde JSON biçiminde saklanır. PHP'nin `fopen`, `fwrite` ve `fclose` komutlarıyla okunur ve yazılır. Aynı anda okunurken yarım veri görünmemesi için dosya kilitlenir.
- **Dış kaynaklar:** Google Fonts (yazı tipleri), cdnjs/Font Awesome (ikonlar), wsrv.nl (fotoğraf hızlandırma, kapatılabilir), YouTube ve Google Haritalar (çerez onayıyla).
- **Test edildi:**
  - Telefon (320–390 px), tablet ve masaüstü ekranları
  - PHP 7.4 ve 8.1
  - Panelden tüm alanların değiştirilip siteye doğru yansıması
  - WhatsApp yönlendirmeleri
  - Çerez onay akışı
  - Hatalı veya eksik veri dosyası durumları
