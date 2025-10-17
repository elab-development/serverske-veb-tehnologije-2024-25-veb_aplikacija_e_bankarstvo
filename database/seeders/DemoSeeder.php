<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\ExchangeRate;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today()->toDateString();

        // 1) Korisnici (admin / operator / customer)
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password'), 'role' => 'admin']
        );

        $operator = User::updateOrCreate(
            ['email' => 'operator@example.com'],
            ['name' => 'Operator', 'password' => bcrypt('password'), 'role' => 'operator']
        );

        $customer = User::updateOrCreate(
            ['email' => 'customer@example.com'],
            ['name' => 'Customer', 'password' => bcrypt('password'), 'role' => 'customer']
        );

        // 2) Kategorije
        $kHrana  = Category::firstOrCreate(['name' => 'Hrana']);
        $kPlata  = Category::firstOrCreate(['name' => 'Plata']);
        $kPrevoz = Category::firstOrCreate(['name' => 'Prevoz']);

        // 3) Računi (za customer-a)
        $accRsd = Account::firstOrCreate(
            ['iban' => 'RS35TEST0000000001'],
            ['user_id' => $customer->id, 'currency' => 'RSD', 'balance' => 80000, 'status' => 'active']
        );

        $accEur = Account::firstOrCreate(
            ['iban' => 'RS35TESTEUR0000001'],
            ['user_id' => $customer->id, 'currency' => 'EUR', 'balance' => 500, 'status' => 'active']
        );

        // 4) Kursna lista (RSD/EUR i obrnuto) – siguran upis za današnji datum
        $this->upsertRate('RSD', 'EUR', $today, 0.0085);
        $this->upsertRate('EUR', 'RSD', $today, round(1 / 0.0085, 6));

        // 5) Transakcije (na RSD računu)
        Transaction::firstOrCreate(
            ['account_id' => $accRsd->id, 'title' => 'Plata', 'booked_at' => $today, 'amount' => 60000, 'type' => 'credit'],
            ['currency' => 'RSD', 'category_id' => $kPlata->id]
        );

        Transaction::firstOrCreate(
            ['account_id' => $accRsd->id, 'title' => 'Kupovina namirnica', 'booked_at' => $today, 'amount' => 1500, 'type' => 'debit'],
            ['currency' => 'RSD', 'category_id' => $kHrana->id]
        );

        // (opciono) Transakcija na EUR računu
        Transaction::firstOrCreate(
            ['account_id' => $accEur->id, 'title' => 'Kafa u EU', 'booked_at' => $today, 'amount' => 3.5, 'type' => 'debit'],
            ['currency' => 'EUR', 'category_id' => $kPrevoz->id]
        );
    }

    private function upsertRate(string $base, string $quote, string $date, float $rate): void
    {
        $existing = ExchangeRate::where('base', $base)
            ->where('quote', $quote)
            ->whereDate('rate_date', $date)
            ->first();

        if ($existing) {
            $existing->rate = $rate;
            $existing->rate_date = $date;
            $existing->save();
        } else {
            ExchangeRate::create([
                'base'      => $base,
                'quote'     => $quote,
                'rate_date' => $date,
                'rate'      => $rate,
            ]);
        }
    }
}
