<?php
class SimpleML {
    
    /**
     * Algoritma: Linear Regression (Least Squares Method)
     * Misi: Memprediksi nilai Y (Omzet) berdasarkan X (Hari ke-n)
     */
    public function predict($historicalData, $daysToPredict = 7) {
        $n = count($historicalData);
        
        if ($n < 2) {
            return []; // Data kurang untuk prediksi
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        $x = 0; // Hari ke-0, 1, 2...
        
        // 1. TRAINING PHASE (Latih Data)
        foreach ($historicalData as $row) {
            $y = $row['total']; // Omzet
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
            
            $x++;
        }

        // Hitung Slope (Kemiringan Garis / b) dan Intercept (Titik Awal / a)
        // Rumus: y = a + bx
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        
        if ($denominator == 0) return []; // Mencegah error bagi nol

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // 2. PREDICTION PHASE (Ramalan)
        $predictions = [];
        $lastDate = end($historicalData)['tgl'];

        for ($i = 0; $i < $daysToPredict; $i++) {
            $futureX = $x + $i; // Hari di masa depan
            $futureY = $intercept + ($slope * $futureX); // Rumus prediksi
            
            // Hindari prediksi minus (bisnis ga mungkin omzet minus)
            $futureY = max(0, $futureY);

            $nextDate = date('Y-m-d', strtotime($lastDate . ' + ' . ($i + 1) . ' days'));

            $predictions[] = [
                'tgl' => $nextDate,
                'prediksi' => round($futureY)
            ];
        }

        return [
            'slope' => $slope, // Jika positif = Tren Naik, Negatif = Tren Turun
            'forecast' => $predictions
        ];
    }
}
?>
