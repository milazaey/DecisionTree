<?php
require 'db.php';     
require 'ID3.php';    

$csvFile = "";

// SKENARIO 1: UPLOAD BARU
if (isset($_FILES['dataset'])) {
    $fileName = $_FILES['dataset']['name'];
    $tmpName  = $_FILES['dataset']['tmp_name'];
    
    if (!is_dir('uploads')) mkdir('uploads');
    $newFileName = time() . '_' . $fileName;
    $targetPath = 'uploads/' . $newFileName;

    if (move_uploaded_file($tmpName, $targetPath)) {
        $stmt = $conn->prepare("INSERT INTO history (nama_file, path_file) VALUES (?, ?)");
        $stmt->execute([$fileName, $targetPath]);
        $csvFile = $targetPath;
    } else {
        echo "Gagal upload file."; exit;
    }

// SKENARIO 2: HISTORY
} elseif (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM history WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $csvFile = $row['path_file'];
        if (!file_exists($csvFile)) { echo "File fisik hilang."; exit; }
    } else {
        header("Location: index.php"); exit;
    }

} else {
    header("Location: index.php"); exit;
}

// PROSES ID3
$handle = fopen($csvFile, "r");
$headers = fgetcsv($handle); 
$data = [];
while (($row = fgetcsv($handle)) !== FALSE) {
    $item = [];
    foreach ($headers as $index => $key) {
        $item[$key] = $row[$index] ?? null;
    }
    $data[] = $item;
}
fclose($handle);

$id3 = new ID3($data, $headers);
$treeStructure = $id3->buildTree();
$logs = $id3->calculationLogs;
$targetCol = end($headers);
$uniqueTargets = array_unique(array_column($data, $targetCol));
sort($uniqueTargets); 

// --- FUNGSI GENERATE RULES ---
function generateRules($node, $chain = "") {
    $rules = [];
    
    if (isset($node['type']) && $node['type'] === 'leaf') {
        // Ya = Hijau (#2E7D32), Tidak = Kuning Gelap (#F9A825)
        $color = (stripos($node['name'], 'ya') !== false || stripos($node['name'], 'yes') !== false || stripos($node['name'], 'lancar') !== false) ? '#2E7D32' : '#F9A825';
        
        $rules[] = "<li style='margin-bottom:8px;'>Jika <b>$chain</b>, maka keputusan: <b style='color:$color;'>" . $node['name'] . "</b></li>";
        return $rules;
    }

    if (isset($node['children'])) {
        foreach ($node['children'] as $child) {
            $attrName = $node['name'];
            $attrValue = $child['name']; 
            $childNode = $child['children'][0]; 
            
            $newChain = ($chain == "") ? "$attrName adalah $attrValue" : "$chain <b>DAN</b> $attrName adalah $attrValue";
            
            $rules = array_merge($rules, generateRules($childNode, $newChain));
        }
    }
    return $rules;
}

$generatedRules = generateRules($treeStructure);
$rootAttribute = $treeStructure['name']; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Analisis Decision Tree</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="style.css">
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/save-svg-as-png/1.4.17/saveSvgAsPng.min.js"></script>
    
    <style>
        #tree-container {
            width: 100%; height: 750px; background-color: #ffffff; 
            position: relative; cursor: grab; overflow: hidden;
            border-radius: 12px; border: none; box-shadow: inset 0 0 20px rgba(0,0,0,0.03); 
        }
        #tree-container:active { cursor: grabbing; }
        
        /* Node Style */
        .node rect, .node circle {
            cursor: move; transition: all 0.2s ease; 
            stroke: #CFD8DC; 
            stroke-width: 1px; 
        }
        .node:hover rect, .node:hover circle { 
            stroke: #37474F; 
            stroke-width: 2.5px; 
        } 
        .node text { 
            font-family: 'Quicksand', sans-serif; 
            font-size: 14px; font-weight: bold; pointer-events: none; text-transform: capitalize; 
        }
        
        .header-viz { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        
        .conclusion-box {
            background: #fff; border-radius: 10px; padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-left: 5px solid #FBC02D; 
        }
        .conclusion-list { padding-left: 20px; line-height: 1.6; color: #444; }
        .influential-attr {
            background: #E0F7FA; color: #006064; padding: 10px 15px;
            border-radius: 6px; display: inline-block; font-weight: bold; margin-bottom: 15px;
        }
        
        /* Style Garis Penghubung */
        .link {
            stroke: #546E7A !important; 
            stroke-width: 2px !important;
        }

        /* [PERBAIKAN UTAMA] Style Label Panah (Rendah, Sedang, dll) */
        .link-label {
            font-family: 'Quicksand', sans-serif;
            fill: #37474F !important; /* Warna Teks Abu Gelap (Gunakan FILL bukan color) */
            font-size: 12px !important;
            font-weight: 800 !important; /* Lebih tebal biar jelas */
            text-anchor: middle;
            
            /* EFEK HALO PUTIH (Biar teks punya background putih di sekelilingnya) */
            stroke: #ffffff;
            stroke-width: 5px; /* Ketebalan outline putih */
            paint-order: stroke; /* Gambar outline dulu baru teks */
            stroke-linejoin: round;
            
            /* Sedikit bayangan biar makin pop-up */
            filter: drop-shadow(0 1px 1px rgba(0,0,0,0.15));
        }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">&laquo; Kembali ke Menu Utama</a>
    <h1>Hasil Analisis Decision Tree</h1>

    <div class="section">
        <h3>1. Dataset Awal (Preview)</h3>
        <div class="table-responsive">
            <table>
                <thead><tr><?php foreach($headers as $h): ?><th><?= $h ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php 
                    $count = 0; 
                    foreach($data as $row): 
                        if($count >= 50) break; 
                    ?>
                    <tr><?php foreach($row as $cell): ?><td><?= $cell ?></td><?php endforeach; ?></tr>
                    <?php $count++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>2. Detail Perhitungan (Entropy & Gain)</h3>
        <?php foreach($logs as $nodeName => $rows): ?>
            <div class="node-box">
                <h4>Perhitungan <?= $nodeName ?></h4>
                <table class="calc-table">
                    <thead>
                        <tr>
                            <th>Atribut</th><th>Nilai</th><th>Jml</th>
                            <?php foreach($uniqueTargets as $t): ?><th><?= $t ?></th><?php endforeach; ?>
                            <th>Entropy</th><th>Gain</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentAttr = "";
                        foreach($rows as $r): 
                            $isNewAttr = ($r['attribute'] != $currentAttr);
                            $currentAttr = $r['attribute'];
                            $rowClass = ($r['attribute'] == 'TOTAL') ? 'row-total' : ($isNewAttr ? 'row-attr-head' : '');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= $isNewAttr ? "<b>".$r['attribute']."</b>" : "" ?></td>
                            <td><?= $r['value'] ?></td>
                            <td><?= $r['count'] ?></td>
                            <?php foreach($uniqueTargets as $t): ?><td><?= $r['distribution'][$t] ?? 0 ?></td><?php endforeach; ?>
                            <td><?= number_format($r['entropy'], 4) ?></td>
                            <td><?= ($r['gain'] !== '') ? "<b>".number_format((float)$r['gain'], 4)."</b>" : "" ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <div class="header-viz">
            <h3>3. Visualisasi Pohon Keputusan</h3>
            <button onclick="downloadTree()" class="btn-download">⬇️ Download PNG</button>
        </div>
        
        <p style="font-size: 13px; color: #555; background: #FFFDE7; padding: 12px; border-radius: 8px; border-left: 5px solid #FBC02D;">
            <b>Tips:</b> Gunakan Scroll Mouse untuk Zoom In/Out. Klik & Geser untuk memindahkan posisi pohon.
        </p>
        <div id="tree-container"></div>
    </div>

    <div class="section">
        <h3>4. Kesimpulan & Aturan Keputusan</h3>
        <div class="conclusion-box">
            <div class="influential-attr">
                🌟 Atribut Paling Berpengaruh: <span style="font-size: 1.1em; text-decoration: underline;"><?= $rootAttribute ?></span>
            </div>
            <p>Berdasarkan pohon keputusan di atas, berikut adalah aturan-aturan (rules) yang terbentuk:</p>
            <ul class="conclusion-list">
                <?php 
                if (!empty($generatedRules)) {
                    foreach($generatedRules as $rule) {
                        echo $rule;
                    }
                } else {
                    echo "<li>Tidak dapat menghasilkan aturan (Pohon mungkin kosong atau terjadi error data).</li>";
                }
                ?>
            </ul>
        </div>
    </div>

</div>

<script>
    const treeData = <?php echo json_encode($treeStructure); ?>;
    const container = document.getElementById("tree-container");
    const width = container.clientWidth; 
    const height = container.clientHeight;

    const zoom = d3.zoom().scaleExtent([0.1, 3]).on("zoom", (e) => {
        g.attr("transform", e.transform);
    });

    const svg = d3.select("#tree-container").append("svg")
        .attr("id", "tree-svg") 
        .attr("width", width)
        .attr("height", height)
        .call(zoom)
        .append("g");

    const g = svg.append("g");

    const treeLayout = d3.tree().nodeSize([230, 150]); 
    const root = d3.hierarchy(treeData, d => {
        if(d.children) return d.children.map(c => { c.children[0].rule = c.name; return c.children[0]; });
    });

    treeLayout(root);

    // Auto Position
    let minX = Infinity, maxX = -Infinity;
    root.descendants().forEach(d => {
        if (d.x < minX) minX = d.x;
        if (d.x > maxX) maxX = d.x;
    });
    const treeWidth = maxX - minX;

    let initialScale = 1; 
    if (treeWidth > width * 0.9) {
        initialScale = (width * 0.9) / treeWidth; 
    }

    const initialTransform = d3.zoomIdentity.translate(width / 2, 150).scale(initialScale);
    d3.select("svg").call(zoom.transform, initialTransform);


    function updateLinks() {
        g.selectAll(".link").attr("d", d3.linkVertical().x(d=>d.x).y(d=>d.y));
        g.selectAll(".link-label").attr("x", d=>(d.source.x+d.target.x)/2).attr("y", d=>(d.source.y+d.target.y)/2);
    }

    g.selectAll(".link").data(root.links()).enter().append("path").attr("class", "link")
        .attr("d", d3.linkVertical().x(d=>d.x).y(d=>d.y))
        .attr("fill","none"); 

    g.selectAll(".link-label").data(root.links()).enter().append("text").attr("class", "link-label")
        .attr("x", d=>(d.source.x+d.target.x)/2).attr("y", d=>(d.source.y+d.target.y)/2).attr("dy",".35em")
        .attr("text-anchor","middle")
        .text(d=>d.target.data.rule);

    const drag = d3.drag()
        .on("start", function(e, d) { d3.select(this).raise().classed("active", true); })
        .on("drag", function(e, d) { d.x = e.x; d.y = e.y; d3.select(this).attr("transform", `translate(${d.x},${d.y})`); updateLinks(); })
        .on("end", function(e, d) { d3.select(this).classed("active", false); });

    const node = g.selectAll(".node").data(root.descendants()).enter().append("g")
        .attr("class", "node").attr("transform", d => `translate(${d.x},${d.y})`).call(drag);

    const boxWidth=140, boxHeight=45;

    node.each(function(d) {
        if (d.data.type === 'leaf') {
            // LINGKARAN (Keputusan)
            d3.select(this).append("circle")
                .attr("r", boxHeight / 1.5)
                        .style("fill", () => {
                const txt = d.data.name.toLowerCase();
                
                // 1. CEK NEGATIF DULU (PENTING: Supaya "Tidak Layak" tidak dianggap "ya")
                // Saya ganti warnanya jadi Pink (#FF80AB atau #F06292)
                if (txt.includes('tidak') || txt.includes('no') || txt.includes('macet')) {
                    return "#ffa2a2ff"; // <--- INI WARNA PINK NYA
                } 

                // 2. BARU CEK POSITIF
                if (txt.includes('ya') || txt.includes('yes') || txt.includes('lancar') || txt.includes('layak')) {
                    return "#89cfc4ff"; // Ini Hijau Tosca
                }
                
                // Default (Abu-abu)
                return "#90A4AE"; 
})
                .style("stroke", "#fff")
                .style("stroke-width", "2px")
                .style("filter", "drop-shadow(0 3px 5px rgba(0,0,0,0.2))");
        } else {
            // KOTAK (Atribut)
            d3.select(this).append("rect")
                .attr("width", boxWidth).attr("height", boxHeight)
                .attr("x", -boxWidth/2).attr("y", -boxHeight/2)
                .attr("rx", 10).attr("ry", 10)
                .style("fill", "#B2DFDB") 
                .style("stroke", "#00897B")
                .style("stroke-width", "1px")
                .style("filter", "drop-shadow(0 3px 5px rgba(0,0,0,0.1))");
        }
    });

    node.append("text").attr("dy", ".35em").attr("y", 0).style("text-anchor", "middle")
        .text(d => d.data.name).style("fill", d => {
            const txt = d.data.name.toLowerCase();
            if (d.data.type === 'leaf') {
                if (txt.includes('tidak') || txt.includes('no') || txt.includes('macet')) return "#333";
                return "white";
            }
            return "#00695C"; 
        });

    function downloadTree() {
        saveSvgAsPng(document.getElementById("tree-svg"), "decision-tree-fixed.png", {
            scale: 2,           
            backgroundColor: "#FFFFFF" 
        });
    }
</script>
</body>
</html>