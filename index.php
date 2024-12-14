<?php
session_start();

function resetMemoryPool($size = 0) {
    $_SESSION['memory_pool'] = [
        ['start' => 0, 'size' => $size, 'allocated' => false]
    ];
    $_SESSION['total_memory'] = $size;
}

function allocateMemory($size) {
    $memoryPool = &$_SESSION['memory_pool'];
    $blockSize = pow(2, ceil(log($size, 2)));

    foreach ($memoryPool as &$block) {
        // Check if the block is large enough and not yet allocated
        if (!$block['allocated'] && $block['size'] >= $blockSize) {
            // Check if a block is already exactly the right size
            if ($block['size'] == $blockSize) {
                $block['allocated'] = true;
                return "Block allocated at address: " . $block['start'];
            }

            // Split the block only if it's larger than the required size
            while ($block['size'] > $blockSize) {
                $block = splitBlock($block);
            }
            $block['allocated'] = true;
            return "Block allocated at address: " . $block['start'];
        }
    }

    return "Allocation failed: No suitable block available.";
}

function deallocateMemory($start) {
    $memoryPool = &$_SESSION['memory_pool'];
    foreach ($memoryPool as &$block) {
        if ($block['allocated'] && $block['start'] == $start) {
            $block['allocated'] = false;
            mergeBlocks();
            return "Block at address $start deallocated successfully.";
        }
    }
    return "Deallocation failed: Invalid block address.";
}

function splitBlock($block) {
    $halfSize = $block['size'] / 2;
    $memoryPool = &$_SESSION['memory_pool'];

    $newBlock = ['start' => $block['start'] + $halfSize, 'size' => $halfSize, 'allocated' => false];

    $block['size'] = $halfSize;
    
    $memoryPool[] = $newBlock;

    return $block;
}

function mergeBlocks() {
    $memoryPool = &$_SESSION['memory_pool'];
    usort($memoryPool, fn($a, $b) => $a['start'] <=> $b['start']);

    for ($i = 0; $i < count($memoryPool) - 1; $i++) {
        $current = &$memoryPool[$i];
        $next = &$memoryPool[$i + 1];

        // If both blocks are free and adjacent
        if (!$current['allocated'] && !$next['allocated'] && $current['size'] == $next['size'] && $current['start'] + $current['size'] == $next['start']) {
            $current['size'] *= 2; // Merge them
            array_splice($memoryPool, $i + 1, 1); // Remove the next block
            $i--; // Check again from the current block
        }
    }
}

function getMemoryStats() {
    $memoryPool = $_SESSION['memory_pool'];
    $totalMemory = $_SESSION['total_memory'];
    $allocatedMemory = 0;
    $freeMemory = 0;

    foreach ($memoryPool as $block) {
        if ($block['allocated']) {
            $allocatedMemory += $block['size'];
        } else {
            $freeMemory += $block['size'];
        }
    }

    return [
        'total_memory' => $totalMemory,
        'allocated_memory' => $allocatedMemory,
        'free_memory' => $freeMemory,
        'block_count' => count($memoryPool),
        'average_block_size' => $totalMemory / count($memoryPool)
    ];
}

if (!isset($_SESSION['memory_pool'])) {
    resetMemoryPool();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_memory'])) {
        resetMemoryPool((int)$_POST['memory_size']);
    } elseif (isset($_POST['allocate_memory'])) {
        $message = allocateMemory((int)$_POST['allocate_size']);
    } elseif (isset($_POST['deallocate_memory'])) {
        $message = deallocateMemory((int)$_POST['deallocate_start']);
    } elseif (isset($_POST['reset_memory'])) {
        resetMemoryPool();
    }
}

$memoryPool = $_SESSION['memory_pool'];
$stats = getMemoryStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buddy System Memory Allocation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="text-center mb-4">Buddy System Memory Allocation</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info text-center mb-4"><?= $message ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Memory Management Functions</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="memory_size" class="form-label">Set Total Memory:</label>
                                <select name="memory_size" id="memory_size" class="form-select">
                                    <option value="512">512 KB</option>
                                    <option value="1024">1024 KB</option>
                                    <option value="2048">2048 KB</option>
                                </select>
                            </div>
                            <button type="submit" name="set_memory" class="btn btn-primary w-100">Set Memory</button>
                        </form>

                        <form method="POST" class="mt-4">
                            <div class="mb-3">
                                <label for="allocate_size" class="form-label">Allocate Memory (KB):</label>
                                <input type="number" name="allocate_size" id="allocate_size" class="form-control" required>
                            </div>
                            <button type="submit" name="allocate_memory" class="btn btn-success w-100">Allocate</button>
                        </form>

                        <form method="POST" class="mt-4">
                            <div class="mb-3">
                                <label for="deallocate_start" class="form-label">Deallocate Memory (Address):</label>
                                <select name="deallocate_start" id="deallocate_start" class="form-select" required>
                                    <?php foreach ($memoryPool as $block): ?>
                                        <?php if ($block['allocated']): ?>
                                            <option value="<?= $block['start'] ?>">Address: <?= $block['start'] ?> (Size: <?= $block['size'] ?> KB)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="deallocate_memory" class="btn btn-warning w-100">Deallocate</button>
                        </form>

                        <form method="POST" class="mt-4">
                            <button type="submit" name="reset_memory" class="btn btn-danger w-100">Reset Memory</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Memory Statistics</div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item">Total Memory: <?= $stats['total_memory'] ?> KB</li>
                            <li class="list-group-item">Allocated Memory: <?= $stats['allocated_memory'] ?> KB</li>
                            <li class="list-group-item">Free Memory: <?= $stats['free_memory'] ?> KB</li>
                            <li class="list-group-item">Total Blocks: <?= $stats['block_count'] ?></li>
                            <li class="list-group-item">Average Block Size: <?= round($stats['average_block_size'], 2) ?> KB</li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Memory Blocks</div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($memoryPool as $block): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card <?= $block['allocated'] ? 'bg-danger' : 'bg-success' ?> text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title m-0">Size: <?= $block['size'] ?> KB</h5>
                                            <p class="card-text m-0">Start: <?= $block['start'] ?> KB</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>