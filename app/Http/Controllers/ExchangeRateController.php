<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ExchangeRateController extends Controller
{
    /**
     * Admin-only: sinhronizuj kurs iz javnog API-ja ili upiši ručno ako je prosleđen "rate".
     * Body primer:
     *  { "base":"RSD", "quote":"EUR", "date":"2025-10-16", "source":"frankfurter" }
     *  ili ručno: { "base":"RSD","quote":"EUR","date":"2025-10-16","rate":0.00853 }
     */
    public function sync(Request $req)
    {
        $data = $req->validate([
            'base'  => 'required|string|size:3',
            'quote' => 'required|string|size:3|different:base',
            'date'  => 'nullable|date',
            'rate'  => 'nullable|numeric|min:0.00000001', // ako je prosleđen, preskačemo API
        ]);

        $base = strtoupper($data['base']);
        $quote = strtoupper($data['quote']);
        $date = $data['date'] ?? Carbon::today()->toDateString();

        // 1) Ako je prosleđen ručni kurs -> koristi njega
        if (!empty($data['rate'])) {
            $rate = (float) $data['rate'];
        } else {
            // 2) Inače pozovi javni API: exchangerate.host (stabilan, podržava RSD)
            $resp = Http::acceptJson()->get("https://api.exchangerate.host/{$date}", [
                'base'    => $base,
                'symbols' => $quote,
            ]);

            if ($resp->failed()) {
                return response()->json([
                    'message' => 'Neuspešan poziv javnog API-ja (exchangerate.host)',
                    'details' => $resp->json(),
                ], 502);
            }

            $json = $resp->json();
            $rate = $json['rates'][$quote] ?? null;

            if (!$rate) {
                // Probaj obrnuti par i invertuj (EUR/RSD -> 1 / rate)
                $resp2 = Http::acceptJson()->get("https://api.exchangerate.host/{$date}", [
                    'base'    => $quote, 
                    'symbols' => $base,
                ]);

                if ($resp2->ok()) {
                    $json2 = $resp2->json();
                    $rev = $json2['rates'][$base] ?? null;
                    if ($rev && (float)$rev > 0) {
                        $rate = round(1 / (float)$rev, 8);
                    }
                }

                // 3) Fallback: probaj /latest umesto specifičnog datuma
                if (!$rate) {
                    $resp3 = Http::acceptJson()->get("https://api.exchangerate.host/latest", [
                        'base'    => $base,
                        'symbols' => $quote,
                    ]);
                    if ($resp3->ok()) {
                        $json3 = $resp3->json();
                        $rate = $json3['rates'][$quote] ?? null;
                    }
                }
                // 4) Fallback: krstarenje preko EUR (cross-rate) — uzmi oba kursa sa base EUR pa izračunaj
                if (!$rate) {
                    $resp4 = Http::acceptJson()->get("https://api.exchangerate.host/{$date}", [
                        'base'    => 'EUR',
                        'symbols' => "{$base},{$quote}",
                    ]);
                    if ($resp4->ok()) {
                        $json4 = $resp4->json();
                        $rBase  = $json4['rates'][$base]  ?? null; // EUR->BASE
                        $rQuote = $json4['rates'][$quote] ?? null; // EUR->QUOTE
                        if ($rBase && $rQuote && (float)$rBase > 0) {
                            // base->quote = (EUR->quote) / (EUR->base)
                            $rate = round(((float)$rQuote) / ((float)$rBase), 8);
                        }
                    }
                }

            }


            if (!$rate) {
                return response()->json([
                    'message' => "Nije pronađen kurs za $base/$quote na $date (exchangerate.host)"
                ], 422);
            }   
        }

       $existing = \App\Models\ExchangeRate::where('base', $base)
            ->where('quote', $quote)
            ->whereDate('rate_date', $date)
            ->first();

        if ($existing) {
            $existing->rate = $rate;
            $existing->save();
            $er = $existing;
        } else {
            $er = \App\Models\ExchangeRate::create([
                'base'      => $base,
                'quote'     => $quote,
                'rate_date' => $date,
                'rate'      => $rate,
            ]);
        }


        return response()->json(['status' => 'ok', 'exchange_rate' => $er], 201);
    }
}
