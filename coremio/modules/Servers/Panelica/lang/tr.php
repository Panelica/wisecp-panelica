<?php

declare(strict_types=1);

return [
    // Ürün formu
    'setting_plan' => 'Panelica paketi',
    'setting_plan_description' => 'Bu üründen açılan her hesabın sınırlarını veren paket.',
    'setting_role' => 'Hesap türü',
    'setting_role_description' => 'Bayi hesabı panelde kendi hesaplarını açabilir.',
    'role_user' => 'Müşteri',
    'role_reseller' => 'Bayi',
    'plans_unavailable' => 'Paketler panelden okunamadı: :error',

    // Tek bir hizmetin kendi formu
    'service_plan_description' => 'Bu hesabin bagli oldugu plan. Burada degistirmek panelde de degistirir.',
    'service_account' => 'Panelica hesabi',

    // Reddetmeler
    'plan_required' => 'Bu üründe Panelica paketi seçili değil. Ürünün Modül sekmesinden seçin.',
    'password_required' => 'Yeni parola gerekli.',
    'no_account_returned' => 'Panel hesabı açtı ama kimlik döndürmedi.',
    'no_account_id' => 'Bu hizmetin henüz Panelica hesabı yok, yapılacak bir şey bulunamadı.',
    'no_server' => 'Henuz bir Panelica sunucusu secilmedi, bu yuzden panele hicbir sey sorulamaz.',
    'plan_unknown' => 'Panelde :plan adli bir plan yok. Urunun Modul sekmesinden bir plan secin',
    'domain_failed' => ':username hesabi acildi, ancak panel :domain alan adini kabul etmedi; site kurulmadi: :error. create yeniden calistirilirsa ayni hesap kullanilir ve alan adi tekrar denenir.',

    // Hizmet sayfası
    'domain' => 'Alan adı',
    'username' => 'Kullanıcı adı',
    'status' => 'Durum',
    'plan' => 'Paket',
    'panel' => 'Kontrol paneli',
    'nameservers' => 'Ad sunucuları',
    'disk' => 'Disk',
    'bandwidth' => 'Trafik',
    'status_active' => 'Etkin',
    'status_suspended' => 'Askıda',
    'status_terminated' => 'Kapatıldı',
    'status_pending' => 'Kuruluyor',

    // Dugmeler ve bir dugme izlenemediginde
    'open_panel' => 'Paneli ac',
    'open_webmail' => 'Webmail',
    'back' => 'Geri',
    'password' => 'Parola',

    // Hos geldin postasi ve yoneticinin hizmet ekrani
    'mail_intro' => 'Hosting hesabiniz hazir. Bilgileri asagida.',
    'service_username_info' => 'Bu hizmetin paneldeki hesabi. Burada degistirmek yalnizca WISECP kaydini degistirir.',
    'panel_unavailable' => 'Panel acilamadi: :error',
    'no_sso_url' => 'Panel bir giris baglantisi dondurmedi.',
];
