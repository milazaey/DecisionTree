<?php

class ID3
{
    public $data = [];
    public $attributes = [];
    public $targetAttribute = '';
    public $calculationLogs = [];
    public $tree = [];

    public function __construct($csvData, $headers)
    {
        $this->data = $csvData;

        // 1. Tentukan Target (Kolom Terakhir)
        $this->targetAttribute = end($headers);

        // 2. Ambil semua header sebagai kandidat atribut
        $tempAttributes = $headers;

        // 3. Buang Kolom Target (Paling Kanan)
        array_pop($tempAttributes);

        // 4. BUANG KOLOM PERTAMA ("No")
        array_shift($tempAttributes);

        $this->attributes = $tempAttributes;
    }

    public function buildTree()
    {
        // --- [UBAH NAMA DI SINI] ---
        // Ganti "Node 1" jadi "Node 1"
        return $this->recursiveBuild($this->data, $this->attributes, "Node 1");
    }

    private function recursiveBuild($data, $attributes, $nodeName)
    {
        if (empty($data)) {
            return ['name' => 'Tidak Diketahui', 'type' => 'leaf'];
        }

        $targets = array_column($data, $this->targetAttribute);

        if (empty($targets)) {
            return ['name' => 'Error Data', 'type' => 'leaf'];
        }

        $uniqueTargets = array_unique($targets);

        // Base Case 1: Jika Entropy 0 (Semua target sama)
        if (count($uniqueTargets) === 1) {
            return ['name' => reset($targets), 'type' => 'leaf'];
        }

        // Base Case 2: Jika atribut habis
        if (empty($attributes)) {
            $counts = array_count_values($targets);
            arsort($counts);
            return ['name' => key($counts), 'type' => 'leaf'];
        }

        // --- [MODIFIKASI DI SINI] ---
        // Cek apakah atribut yang tersisa tinggal 1?
        if (count($attributes) === 1) {
            // JIKA TINGGAL 1: Langsung pilih atribut itu tanpa hitung Gain & tanpa Log
            $bestAttr = reset($attributes);
        } else {
            // JIKA LEBIH DARI 1: Lakukan perhitungan Entropy & Gain seperti biasa

            $globalEntropy = $this->calculateEntropy($data);
            $gains = [];
            $logDetails = [];

            // Log Total
            $targetCounts = array_count_values($targets);
            $logDetails[] = [
                'attribute' => 'TOTAL',
                'value' => 'Total',
                'count' => count($data),
                'distribution' => $targetCounts,
                'entropy' => $globalEntropy,
                'gain' => '-'
            ];

            // Loop Atribut
            foreach ($attributes as $attrName) {
                $values = array_column($data, $attrName);
                $uniqueValues = array_unique($values);

                $weightedEntropy = 0;
                $attrLog = [];

                foreach ($uniqueValues as $val) {
                    $subset = array_filter($data, function ($row) use ($attrName, $val) {
                        return isset($row[$attrName]) && $row[$attrName] == $val;
                    });

                    $subsetEntropy = $this->calculateEntropy($subset);
                    $weight = count($subset) / count($data);
                    $weightedEntropy += $weight * $subsetEntropy;

                    $subTargets = array_column($subset, $this->targetAttribute);
                    $subDist = array_count_values($subTargets);

                    $attrLog[] = [
                        'attribute' => $attrName,
                        'value' => $val,
                        'count' => count($subset),
                        'distribution' => $subDist,
                        'entropy' => $subsetEntropy,
                        'gain' => ''
                    ];
                }

                $gain = $globalEntropy - $weightedEntropy;
                $gains[$attrName] = $gain;

                if (!empty($attrLog)) {
                    $attrLog[0]['gain'] = $gain;
                }

                $logDetails = array_merge($logDetails, $attrLog);
            }

            // Simpan log HANYA jika kita melakukan perhitungan (atribut > 1)
            $this->calculationLogs[$nodeName] = $logDetails;

            // Cek jika tidak ada gain valid
            if (empty($gains)) {
                $counts = array_count_values($targets);
                arsort($counts);
                return ['name' => key($counts), 'type' => 'leaf'];
            }

            // --- PILIH ATRIBUT TERBAIK ---
            arsort($gains);
            $bestAttr = key($gains);
            $bestGain = current($gains);

            // Jika Gain 0, stop
            if ($bestGain == 0) {
                $counts = array_count_values($targets);
                arsort($counts);
                return ['name' => key($counts), 'type' => 'leaf'];
            }
        }
        // --- [AKHIR MODIFIKASI] ---

        $node = ['name' => $bestAttr, 'children' => []];
        $newAttributes = array_diff($attributes, [$bestAttr]);

        $bestAttrValues = array_unique(array_column($data, $bestAttr));

        $counter = 1;
        foreach ($bestAttrValues as $val) {
            $subset = array_filter($data, function ($row) use ($bestAttr, $val) {
                return $row[$bestAttr] == $val;
            });

            $subset = array_values($subset);

            if (empty($subset)) continue;

            $childNodeName = $nodeName . "." . $counter++;
            $child = $this->recursiveBuild($subset, $newAttributes, $childNodeName);

            $node['children'][] = [
                'name' => $val,
                'children' => [$child]
            ];
        }

        return $node;
    }

    private function calculateEntropy($data)
    {
        if (empty($data)) return 0;

        $targets = array_column($data, $this->targetAttribute);
        $counts = array_count_values($targets);
        $total = count($data);

        $entropy = 0;
        foreach ($counts as $count) {
            $p = $count / $total;
            $entropy -= $p * log($p, 2);
        }
        return $entropy;
    }
}
