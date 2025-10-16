<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransferController extends Controller
{
    public function store(Request $req)
    {
        $data = $req->validate([
            'source_account_id' => 'required|integer|different:target_account_id',
            'target_account_id' => 'required|integer',
            'amount'            => 'required|numeric|min:0.01',
            'title'             => 'nullable|string|max:255',
        ]);

        // 1) Učitaj naloge i proveri vlasništvo/validnost
        $source = Account::findOrFail($data['source_account_id']);
        $target = Account::findOrFail($data['target_account_id']);

        // Vlasnik izvornog mora biti trenutni korisnik
        abort_unless($source->user_id === $req->user()->id, 403, 'Niste vlasnik izvornog računa.');

        // Oba moraju biti aktivna
        foreach ([$source, $target] as $acc) {
            abort_if($acc->status !== 'active', 422, 'Račun je blokiran.');
        }

        // 2) FX kurs (ako su različite valute)
        $rateDate = Carbon::today()->toDateString();
        $fxRate = $this->resolveFxRate($source->currency, $target->currency, $rateDate); // može vratiti 1.0 za iste valute

        // 3) Izračunaj iznose
        $amountOut = round((float)$data['amount'], 2);
        $amountIn  = round($amountOut * $fxRate, 2);

        // 4) Atomicnost i zaključavanje
        return DB::transaction(function () use ($req, $source, $target, $amountOut, $amountIn, $fxRate, $data, $rateDate) {
            // Zaključaj oba naloga da izbegnemo race conditions
            $locked = Account::whereIn('id', [$source->id, $target->id])->lockForUpdate()->get();
            $src = $locked->firstWhere('id', $source->id);
            $dst = $locked->firstWhere('id', $target->id);

            // Provera pokrića
            abort_if($src->balance < $amountOut, 422, 'Nedovoljno sredstava.');

            // Ažuriraj stanja
            $src->balance = round($src->balance - $amountOut, 2);
            $dst->balance = round($dst->balance + $amountIn, 2);
            $src->save();
            $dst->save();

            $today = Carbon::today()->toDateString();
            $titleOut = $data['title'] ?? ('Transfer na ' . $dst->iban);
            $titleIn  = $data['title'] ?? ('Transfer sa ' . $src->iban);

            // Knjiženja (2 stavke)
            $debit = Transaction::create([
                'account_id' => $src->id,
                'category_id'=> null,
                'type'       => 'debit',
                'amount'     => $amountOut,
                'currency'   => $src->currency,
                'title'      => $titleOut,
                'booked_at'  => $today,
            ]);

            $credit = Transaction::create([
                'account_id' => $dst->id,
                'category_id'=> null,
                'type'       => 'credit',
                'amount'     => $amountIn,
                'currency'   => $dst->currency,
                'title'      => $titleIn,
                'booked_at'  => $today,
            ]);

            return response()->json([
                'status'      => 'ok',
                'debited'     => ['account_id' => $src->id, 'amount' => $amountOut, 'currency' => $src->currency],
                'credited'    => ['account_id' => $dst->id, 'amount' => $amountIn,  'currency' => $dst->currency],
                'fx'          => [
                    'rate_used' => $fxRate,
                    'date'      => $rateDate,
                    'pair'      => $src->currency . '/' . $dst->currency,
                ],
                'transactions'=> ['debit_id' => $debit->id, 'credit_id' => $credit->id],
            ], 201);
        });
    }

    private function resolveFxRate(string $base, string $quote, string $date): float
    {
        if ($base === $quote) return 1.0;

        $rate = ExchangeRate::where('base', $base)
            ->where('quote', $quote)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->value('rate');

        if ($rate) return (float)$rate;

        // Probaj obrnuti par
        $inverse = ExchangeRate::where('base', $quote)
            ->where('quote', $base)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->value('rate');

        if ($inverse) {
            $val = (float)$inverse;
            abort_if($val <= 0, 422, 'Neispravan kurs.');
            return round(1 / $val, 8);
        }

        abort(422, 'Nije pronađen važeći kurs za ' . $base . '/' . $quote . ' na datum ' . $date . '.');
    }
}
