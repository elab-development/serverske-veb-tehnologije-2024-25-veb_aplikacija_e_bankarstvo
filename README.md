# E-Banking (Laravel API)

Backend API za e-bankarstvo (bez frontenda). Podržava:
- Registraciju/prijavu (Sanctum tokeni)
- Račune (RSD/EUR…), stanje i transakcije
- Prenos sredstava (i sa deviznom konverzijom)
- Kursnu listu (ručni unos + pokušaj javnog API-ja sa fallbackovima)
- Pretragu transakcija po nazivu/kategoriji/tipu/opsegu datuma

## Tehnologije
- PHP 8.x, Laravel 11.x
- SQLite (podrazumevano), Sanctum
- Testiranje kroz Postman ili PowerShell/Curl

---

## Pokretanje

1) Kloniraj repo i uđi u projekat  
```bash
git clone <URL_TVOG_REPOZITORIJUMA>
cd e-banking
