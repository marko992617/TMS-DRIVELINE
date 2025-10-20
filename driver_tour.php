<?php
// Add at top of file
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

session_start();
require 'config.php';

// ===== BUG FIX: Detect POST size overflow =====
// When uploading 3+ images, POST data can exceed server limits
// This causes $_POST and $_FILES to be completely empty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $postMaxSize = ini_get('post_max_size');
    $uploadMaxSize = ini_get('upload_max_filesize');
    $contentLength = $_SERVER['CONTENT_LENGTH'];
    
    $error_message = "GREŠKA: Ukupna veličina podataka (" . round($contentLength / 1024 / 1024, 2) . " MB) premašuje server limit ({$postMaxSize}).<br><br>";
    $error_message .= "Molimo:<br>";
    $error_message .= "1. Smanjite broj slika ili ih kompresujte pre upload-a<br>";
    $error_message .= "2. Koristite slike manje od 5MB svaka<br>";
    $error_message .= "3. Ako problem i dalje postoji, kontaktirajte administratora da poveća post_max_size na serveru";
    
    // Show error without requiring session check
    ?>
    <!DOCTYPE html>
    <html lang="sr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Greška pri upload-u</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Greška pri upload-u</h4>
                        <p><?= $error_message ?></p>
                        <hr>
                        <p class="mb-0">
                            <strong>Tehnički detalji:</strong><br>
                            Server post_max_size: <?= $postMaxSize ?><br>
                            Server upload_max_filesize: <?= $uploadMaxSize ?><br>
                            Veličina poslanih podataka: <?= round($contentLength / 1024 / 1024, 2) ?> MB
                        </p>
                    </div>
                    <a href="javascript:history.back()" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Nazad
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header('Location: drivers_login.php');
    exit;
}

$driver_id = $_SESSION['driver_id'];
$tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;

if ($tour_id <= 0) {
    header('Location: driver_dashboard.php');
    exit;
}

// Fetch tour data
$stmt = $pdo->prepare("
    SELECT t.*, v.plate as vehicle_plate, d.name as driver_name
    FROM tours t
    LEFT JOIN vehicles v ON t.vehicle_id = v.id
    LEFT JOIN drivers d ON t.driver_id = d.id
    WHERE t.id = ? AND t.driver_id = ?
");
$stmt->execute([$tour_id, $driver_id]);
$tour = $stmt->fetch();

if (!$tour) {
    header('Location: driver_dashboard.php');
    exit;
}

// Check if already submitted
$stmt = $pdo->prepare("SELECT * FROM driver_submissions WHERE tour_id = ? AND driver_id = ?");
$stmt->execute([$tour_id, $driver_id]);
$submission = $stmt->fetch();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$submission) {
    $waybill_number = trim($_POST['waybill_number'] ?? '');
    $start_km = (int)($_POST['start_km'] ?? 0);
    $end_km = (int)($_POST['end_km'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    
    // Validation
    $errors = [];
    
    // Check waybill number
    if (empty($waybill_number)) {
        $errors[] = 'Broj tovarnog lista je obavezan';
    }
    
    // Check kilometers
    if ($start_km <= 0) {
        $errors[] = 'Početna kilometraža mora biti veća od 0';
    }
    
    if ($end_km <= 0) {
        $errors[] = 'Završna kilometraža mora biti veća od 0';
    }
    
    if ($end_km <= $start_km) {
        $errors[] = 'Završna kilometraža mora biti veća od početne';
    }
    
    // Handle image uploads
    $uploaded_images = [];
    $image_errors = [];
    
    if (!empty($_FILES['images']['name'][0])) {
        // Create upload directory if it doesn't exist
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        $file_count = count($_FILES['images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['images']['name'][$i];
                $file_tmp = $_FILES['images']['tmp_name'][$i];
                $file_size = $_FILES['images']['size'][$i];
                
                // Validate file size
                if ($file_size > MAX_FILE_SIZE) {
                    $image_errors[] = "Slika {$file_name} je prevelika (max 50MB)";
                    continue;
                }
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($file_tmp);
                
                if (!in_array($file_type, $allowed_types)) {
                    $image_errors[] = "Slika {$file_name} nije validnog formata";
                    continue;
                }
                
                // Generate unique filename
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = 'tour_' . $tour_id . '_' . uniqid() . '.' . $extension;
                $destination = UPLOAD_DIR . $unique_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    $uploaded_images[] = $destination;
                } else {
                    $image_errors[] = "Greška pri uploadu slike {$file_name}";
                }
            } elseif ($_FILES['images']['error'][$i] === UPLOAD_ERR_INI_SIZE || $_FILES['images']['error'][$i] === UPLOAD_ERR_FORM_SIZE) {
                $image_errors[] = "Slika " . $_FILES['images']['name'][$i] . " je prevelika";
            }
        }
    }
    
    // Check if at least one image is uploaded
    if (empty($uploaded_images)) {
        $errors[] = 'Morate dodati najmanje jednu sliku';
    }
    
    // Merge image errors with validation errors
    $errors = array_merge($errors, $image_errors);
    
    if (empty($errors)) {
        try {
            // Insert submission
            $stmt = $pdo->prepare("
                INSERT INTO driver_submissions 
                (driver_id, tour_id, waybill_number, start_km, end_km, note, images_json, submitted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $images_json = json_encode($uploaded_images);
            $stmt->execute([$driver_id, $tour_id, $waybill_number, $start_km, $end_km, $note, $images_json]);
            
            // Refresh submission data
            $stmt = $pdo->prepare("SELECT * FROM driver_submissions WHERE tour_id = ? AND driver_id = ?");
            $stmt->execute([$tour_id, $driver_id]);
            $submission = $stmt->fetch();
            
            // Kreiraj PDF sa slikama
            require_once 'pdf_helpers.php';
            $pdfPath = create_waybill_pdf($submission, $tour);
            
            if ($pdfPath) {
                // Ažuriraj putanju PDF-a u bazi
                $stmt = $pdo->prepare("UPDATE driver_submissions SET image_path = ? WHERE id = ?");
                $stmt->execute([$pdfPath, $submission['id']]);
                $success_message = 'Tura je uspešno razdužena i PDF je kreiran!';
            } else {
                $success_message = 'Tura je uspešno razdužena!';
            }
            
        } catch (Exception $e) {
            $error_message = 'Greška pri čuvanju: ' . $e->getMessage();
            // Delete uploaded images on error
            foreach ($uploaded_images as $img) {
                if (file_exists($img)) {
                    unlink($img);
                }
            }
        }
    } else {
        $error_message = implode('<br>', $errors);
        // Delete uploaded images on error
        foreach ($uploaded_images as $img) {
            if (file_exists($img)) {
                unlink($img);
            }
        }
    }
}

function formatDate($date) {
    return $date ? date('d.m.Y', strtotime($date)) : '';
}

function formatTime($datetime) {
    return $datetime ? date('H:i', strtotime($datetime)) : '';
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Razduženje ture</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-truck me-2"></i>
                        Razduženje ture #<?= $tour['id'] ?>
                    </h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $success_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error_message ?>
                        </div>
                    <?php endif; ?>

                    <!-- Back to dashboard button -->
                    <div class="mb-3">
                        <a href="driver_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Nazad na dashboard
                        </a>
                    </div>

                    <!-- Tour info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Informacije o turi</h6>
                            <p><strong>Datum:</strong> <?= formatDate($tour['date']) ?></p>
                            <p><strong>Vozilo:</strong> <?= htmlspecialchars($tour['vehicle_plate'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Vreme utovara:</strong> <?= formatTime($tour['loading_time']) ?></p>
                            <p><strong>Mesto utovara:</strong> <?= htmlspecialchars($tour['loading_loc']) ?></p>
                            <p><strong>Mesto istovara:</strong> <?= htmlspecialchars($tour['unloading_loc']) ?></p>
                        </div>
                    </div>

                    <?php if ($submission): ?>
                        <!-- Show submission details -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Tura je već razdužena</h6>
                            <p><strong>Broj tovarnog lista:</strong> <?= htmlspecialchars($submission['waybill_number']) ?></p>
                            <p><strong>Kilometraža:</strong> <?= ($submission['end_km'] - $submission['start_km']) ?> km (<?= $submission['start_km'] ?> - <?= $submission['end_km'] ?>)</p>
                            <p><strong>Datum razduženja:</strong> <?= date('d.m.Y H:i', strtotime($submission['submitted_at'])) ?></p>
                            <?php if ($submission['note']): ?>
                                <p><strong>Napomena:</strong> <?= htmlspecialchars($submission['note']) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($submission['images_json']): ?>
                                <?php $images = json_decode($submission['images_json'], true); ?>
                                <?php if ($images && count($images) > 0): ?>
                                    <p><strong>Slike:</strong></p>
                                    <div class="row">
                                        <?php foreach ($images as $image): ?>
                                            <?php if (file_exists($image)): ?>
                                                <div class="col-md-3 mb-2">
                                                    <img src="<?= htmlspecialchars($image) ?>" class="img-fluid rounded" alt="Slika ture">
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Show form for submission -->
                        <form method="POST" enctype="multipart/form-data" id="dischargeForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="waybill_number" class="form-label">
                                            Broj tovarnog lista <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="waybill_number" name="waybill_number" 
                                               value="<?= htmlspecialchars($_POST['waybill_number'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="start_km" class="form-label">
                                            Početna km <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" class="form-control" id="start_km" name="start_km" 
                                               value="<?= htmlspecialchars($_POST['start_km'] ?? '') ?>" required min="1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="end_km" class="form-label">
                                            Završna km <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" class="form-control" id="end_km" name="end_km" 
                                               value="<?= htmlspecialchars($_POST['end_km'] ?? '') ?>" required min="1">
                                    </div>
                                </div>
                            </div>

                            <!-- Km difference display -->
                            <div id="kmDifference" class="d-none mb-3">
                                Razlika: <strong><span id="kmDiffValue"></span> km</strong>
                            </div>

                            <!-- Image upload section with camera support -->
                            <div class="mb-3">
                                <label class="form-label">
                                    Slike tovarnog lista <span class="text-danger">*</span>
                                </label>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <button type="button" class="btn btn-primary w-100 py-2" id="openCamera">
                                                    <i class="fas fa-camera me-2"></i>Otvori kameru
                                                </button>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <button type="button" class="btn btn-secondary w-100 py-2" id="openGallery">
                                                    <i class="fas fa-images me-2"></i>Izaberi iz galerije
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-muted small mt-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Dodajte slike jedna po jedna. Slike će biti automatski kompresovane pre upload-a.
                                        </div>
                                    </div>
                                </div>

                                <!-- Hidden file inputs -->
                                <input type="file" class="d-none" id="cameraInput" accept="image/*" capture="environment">
                                <input type="file" class="d-none" id="galleryInput" multiple accept="image/*">

                                <!-- Images preview grid -->
                                <div id="imagesGrid" class="row mt-3"></div>
                                
                                <!-- Hidden input to store images for form submission -->
                                <input type="file" id="hiddenImagesInput" name="images[]" multiple class="d-none">
                            </div>

                            <div class="mb-3">
                                <label for="note" class="form-label">Napomena</label>
                                <textarea class="form-control" id="note" name="note" rows="3"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="driver_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Nazad
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Razduži turu
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let capturedImages = [];
    
    const openCameraBtn = document.getElementById('openCamera');
    const openGalleryBtn = document.getElementById('openGallery');
    const cameraInput = document.getElementById('cameraInput');
    const galleryInput = document.getElementById('galleryInput');
    const imagesGrid = document.getElementById('imagesGrid');
    const hiddenInput = document.getElementById('hiddenImagesInput');

    // ===== IMAGE COMPRESSION FUNCTION =====
    // This compresses images to prevent POST size overflow
    function compressImage(file, maxSizeMB = 2) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;
                    
                    // Resize if too large
                    const maxDimension = 1920;
                    if (width > maxDimension || height > maxDimension) {
                        if (width > height) {
                            height = (height / width) * maxDimension;
                            width = maxDimension;
                        } else {
                            width = (width / height) * maxDimension;
                            height = maxDimension;
                        }
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Try different quality levels to meet size requirement
                    let quality = 0.8;
                    canvas.toBlob(function(blob) {
                        // If still too large, reduce quality
                        if (blob.size > maxSizeMB * 1024 * 1024 && quality > 0.3) {
                            quality = 0.6;
                            canvas.toBlob(function(blob2) {
                                if (blob2.size > maxSizeMB * 1024 * 1024 && quality > 0.3) {
                                    quality = 0.4;
                                    canvas.toBlob(function(blob3) {
                                        const compressedFile = new File([blob3], file.name, {
                                            type: 'image/jpeg',
                                            lastModified: Date.now()
                                        });
                                        resolve(compressedFile);
                                    }, 'image/jpeg', quality);
                                } else {
                                    const compressedFile = new File([blob2], file.name, {
                                        type: 'image/jpeg',
                                        lastModified: Date.now()
                                    });
                                    resolve(compressedFile);
                                }
                            }, 'image/jpeg', quality);
                        } else {
                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        }
                    }, 'image/jpeg', quality);
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    // Open camera
    openCameraBtn.addEventListener('click', function() {
        cameraInput.click();
    });

    // Handle camera capture with compression
    cameraInput.addEventListener('change', async function() {
        const files = Array.from(this.files);
        for (const file of files) {
            if (file.type.startsWith('image/')) {
                try {
                    const compressedFile = await compressImage(file);
                    const timestamp = new Date().toLocaleString('sr-RS', {
                        year: 'numeric',
                        month: '2-digit', 
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    addImageToGrid(compressedFile, `Kamera - ${timestamp}`);
                    capturedImages.push(compressedFile);
                } catch (error) {
                    console.error('Greška pri kompresiji slike:', error);
                    alert('Greška pri obradi slike. Pokušajte ponovo.');
                }
            }
        }
        updateHiddenInput();
        this.value = '';
    });

    // Open gallery
    openGalleryBtn.addEventListener('click', function() {
        galleryInput.click();
    });

    // Handle gallery selection with compression
    galleryInput.addEventListener('change', async function() {
        const files = Array.from(this.files);
        for (const file of files) {
            if (file.type.startsWith('image/')) {
                try {
                    const compressedFile = await compressImage(file);
                    addImageToGrid(compressedFile, file.name);
                    capturedImages.push(compressedFile);
                } catch (error) {
                    console.error('Greška pri kompresiji slike:', error);
                    alert('Greška pri obradi slike: ' + file.name);
                }
            }
        }
        updateHiddenInput();
        this.value = '';
    });

    // Add image to grid
    function addImageToGrid(file, displayName) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageId = 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const div = document.createElement('div');
            div.className = 'col-md-4 col-sm-6 mb-3';
            div.id = imageId;
            
            const sizeKB = (file.size / 1024).toFixed(1);
            const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
            const sizeText = file.size > 1024 * 1024 ? sizeMB + ' MB' : sizeKB + ' KB';
            
            div.innerHTML = `
                <div class="card">
                    <img src="${e.target.result}" class="card-img-top" style="height: 150px; object-fit: cover;">
                    <div class="card-body p-2">
                        <h6 class="card-title small mb-1">${displayName}</h6>
                        <p class="card-text small text-muted mb-2">Veličina: ${sizeText}</p>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeImage('${imageId}', '${file.name}')">
                            <i class="fas fa-trash me-1"></i>Ukloni
                        </button>
                    </div>
                </div>
            `;
            imagesGrid.appendChild(div);
        };
        reader.readAsDataURL(file);
    }

    // Remove image
    window.removeImage = function(imageId, fileName) {
        const element = document.getElementById(imageId);
        if (element) {
            element.remove();
        }
        
        capturedImages = capturedImages.filter(file => file.name !== fileName);
        updateHiddenInput();
    };

    // Update hidden input with current images
    function updateHiddenInput() {
        const dt = new DataTransfer();
        capturedImages.forEach(file => {
            dt.items.add(file);
        });
        hiddenInput.files = dt.files;
    }

    // Form validation
    const form = document.getElementById('dischargeForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const waybill = document.getElementById('waybill_number').value.trim();
            const startKm = parseInt(document.getElementById('start_km').value);
            const endKm = parseInt(document.getElementById('end_km').value);
            
            let errors = [];
            
            if (!waybill) {
                errors.push('Broj tovarnog lista je obavezan');
            }
            
            if (!startKm || startKm <= 0) {
                errors.push('Početna kilometraža mora biti veća od 0');
            }
            
            if (!endKm || endKm <= 0) {
                errors.push('Završna kilometraža mora biti veća od 0');
            }
            
            if (endKm <= startKm) {
                errors.push('Završna kilometraža mora biti veća od početne');
            }
            
            if (capturedImages.length === 0) {
                errors.push('Morate dodati najmanje jednu sliku');
            }
            
            // Check total size
            let totalSize = 0;
            capturedImages.forEach(file => {
                totalSize += file.size;
            });
            
            // Warn if total size is large (but allow it - compression should help)
            if (totalSize > 20 * 1024 * 1024) {
                if (!confirm('Ukupna veličina slika je ' + (totalSize / 1024 / 1024).toFixed(2) + ' MB. Ovo može potrajati. Nastaviti?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Molimo ispravite sledeće greške:\n\n' + errors.join('\n'));
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Čuva se...';
        });
    }

    // Kalkulacija razlike u kilometraži
    const startKmInput = document.getElementById('start_km');
    const endKmInput = document.getElementById('end_km');
    const kmDifferenceDiv = document.getElementById('kmDifference');
    const kmDiffValue = document.getElementById('kmDiffValue');

    function updateKmDifference() {
        const startKm = parseInt(startKmInput.value) || 0;
        const endKm = parseInt(endKmInput.value) || 0;
        
        if (startKm > 0 && endKm > 0 && endKm > startKm) {
            const difference = endKm - startKm;
            kmDiffValue.textContent = difference;
            kmDifferenceDiv.classList.remove('d-none');
            kmDifferenceDiv.className = 'alert alert-success';
        } else if (startKm > 0 && endKm > 0 && endKm <= startKm) {
            kmDifferenceDiv.classList.remove('d-none');
            kmDifferenceDiv.className = 'alert alert-warning';
            kmDiffValue.textContent = 'Završna km mora biti veća od početne';
        } else {
            kmDifferenceDiv.classList.add('d-none');
        }
    }

    startKmInput.addEventListener('input', updateKmDifference);
    endKmInput.addEventListener('input', updateKmDifference);
});
</script>
</body>
</html>
