# MarsOtomasyon scheduler

Sunucunun cron tablosunda tek satır yeterlidir:

```cron
* * * * * cd /var/www/mars && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker systemd ile sürekli çalışır; scheduler queue worker'ın yerine geçmez.
