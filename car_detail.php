<?php
session_start();
require_once 'config.php';

$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();

$carId = isset($_GET['id']) ? $_GET['id'] : '';
$car = $carId ? getCarById($carId) : null;

if (!$car) {
    header('Location: index.php');
    exit;
}

$fromSearch = isset($_GET['from']) && $_GET['from'] === 'search';

// Determine seller info
if (!empty($car['seller_full_name']) || !empty($car['seller_name'])) {
    $sellerName = $car['seller_full_name'] ?? $car['seller_name'];
    $sellerPicture = $car['seller_picture'] ?? null;
    $isVerified = $car['seller_verified'] ?? false;
    $isDealership = ($car['seller_type'] ?? 'individual') === 'dealership';
    $dealershipName = $car['dealership_name'] ?? null;
    if ($isDealership && $dealershipName) {
        $sellerName = $dealershipName;
    }
} else {
    $sellerName = 'Seller_' . substr($car['id'], -4);
    $sellerPicture = null;
    $isVerified = false;
    $isDealership = false;
}

// Check if listing is saved
$isSaved = false;
if ($isLoggedIn) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("SELECT id FROM saved_listings WHERE user_id = ? AND car_id = ?");
        $stmt->execute([$currentUser['id'], $carId]);
        $isSaved = $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Error checking saved listing: " . $e->getMessage());
    }
}

$carUrl = BASE_URL . '/car_detail.php?id=' . urlencode($carId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model'] . ' ' . $car['year'] . ' - $' . number_format($car['price'])); ?>">
    <title><?php echo htmlspecialchars($car['make'] . ' ' . $car['model'] . ' ' . $car['year']); ?> - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <!-- Modular CSS -->
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/detail.css">
    <link rel="stylesheet" href="css/buttons.css">
</head>
<body>
    <!-- Detail Container -->
    <div class="detail-container">
        <!-- Header with Back Button -->
        <header class="detail-header">
            <button class="back-btn" id="backButton" onclick="goBack()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <h1>Car Details</h1>
            <div style="width: 40px;"></div> <!-- Spacer for centering -->
        </header>

        <!-- Car Images Gallery -->
        <div class="detail-images">
            <?php if (!empty($car['images'])): ?>
                <div class="image-gallery">
                    <?php foreach ($car['images'] as $index => $image): ?>
                            <div class="gallery-item <?php echo $index === 0 ? 'active' : ''; ?>" role="img" aria-label="Image <?php echo $index + 1; ?> of <?php echo count($car['images']); ?>">
                            <img src="<?php echo htmlspecialchars($image); ?>" 
                                 alt="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model'] . ' ' . $car['year']); ?> - Image <?php echo $index + 1; ?> of <?php echo count($car['images']); ?>"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'400\'%3E%3Crect fill=\'%23f0f0f0\' width=\'400\' height=\'400\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'20\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\'%3ENo Image%3C/text%3E%3C/svg%3E';">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($car['images']) > 1): ?>
                    <div class="gallery-thumbnails">
                        <?php foreach ($car['images'] as $index => $image): ?>
                            <button type="button" class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="showImage(<?php echo $index; ?>)" aria-label="View image <?php echo $index + 1; ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     alt="Thumbnail <?php echo $index + 1; ?>"
                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect fill=\'%23f0f0f0\' width=\'100\' height=\'100\'/%3E%3C/svg%3E';">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-image-large">
                    <span>No Images Available</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Car Information -->
        <div class="detail-content">
            <!-- Seller Info -->
            <div class="detail-section">
                <div class="seller-info">
                    <?php if ($sellerPicture): ?>
                        <img src="<?php echo htmlspecialchars($sellerPicture); ?>" 
                             alt="<?php echo htmlspecialchars($sellerName); ?>" 
                             class="user-avatar large-img">
                    <?php else: ?>
                        <div class="user-avatar large"><?php echo strtoupper(substr($sellerName, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="seller-details">
                        <div class="seller-name">
                            <?php echo htmlspecialchars($sellerName); ?>
                            <?php if ($isVerified): ?>
                                <span class="verified-badge" title="Verified Seller">✓</span>
                            <?php endif; ?>
                            <?php if ($isDealership): ?>
                                <span class="dealership-badge" title="Dealership">🏢</span>
                            <?php endif; ?>
                        </div>
                        <div class="seller-label">
                            <?php echo $isDealership ? 'Dealership' : 'Seller'; ?>
                            <?php if (!empty($car['city']) && !empty($car['country'])): ?>
                                <span style="color: var(--text-secondary);"> • <?php echo htmlspecialchars($car['city'] . ', ' . $car['country']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Title and Price -->
            <div class="detail-section">
                <h2 class="car-title"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?></h2>
                <div class="car-price-large">$<?php echo number_format($car['price']); ?></div>
            </div>

            <!-- Car Specifications -->
            <div class="detail-section">
                <h3 class="section-title">Specifications</h3>
                <div class="specs-grid">
                    <?php if (!empty($car['vehicle_type'])): ?>
                    <div class="spec-item">
                        <div class="spec-label">Vehicle Type</div>
                        <div class="spec-value"><?php echo htmlspecialchars(ucfirst($car['vehicle_type'] === 'motorcycle' ? 'Motorcycle' : $car['vehicle_type'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="spec-item">
                        <div class="spec-label">Make</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['make']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Model</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['model']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Year</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['year']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Mileage</div>
                        <div class="spec-value"><?php echo number_format($car['mileage']); ?> miles</div>
                    </div>
                    <?php if (!empty($car['transmission'])): ?>
                    <div class="spec-item">
                        <div class="spec-label">Transmission</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['transmission']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($car['fuel_type'])): ?>
                    <div class="spec-item">
                        <div class="spec-label">Fuel Type</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['fuel_type']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="spec-item">
                        <div class="spec-label">Color</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['color']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Price</div>
                        <div class="spec-value price-value">$<?php echo number_format($car['price']); ?></div>
                    </div>
                    <?php if (!empty($car['country'])): ?>
                    <div class="spec-item">
                        <div class="spec-label">Country</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['country']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($car['city'])): ?>
                    <div class="spec-item">
                        <div class="spec-label">City</div>
                        <div class="spec-value"><?php echo htmlspecialchars($car['city']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="detail-section">
                <div class="action-buttons">
                    <?php if ($isLoggedIn): ?>
                        <button class="action-btn save-btn-detail <?php echo $isSaved ? 'saved' : ''; ?>" 
                                data-car-id="<?php echo htmlspecialchars($carId); ?>" 
                                aria-label="Save listing"
                                style="background: var(--glass-bg); border: 1px solid var(--border-color); padding: 12px; border-radius: 12px; cursor: pointer;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="<?php echo $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                        </button>
                    <?php endif; ?>
                    <button class="btn-primary btn-full" id="contactSellerBtn">
                        Contact Seller
                    </button>
                    <button class="btn-secondary btn-full" onclick="goBack()">
                        <?php echo $fromSearch ? 'Back to Search' : 'Back to Feed'; ?>
                    </button>
                </div>
            </div>
            
            <!-- Contact Seller Modal -->
            <div id="contactModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Contact Seller</h2>
                        <button class="modal-close" onclick="closeContactModal()" aria-label="Close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="contact-options">
                        <?php if (!empty($car['seller_whatsapp'])): ?>
                            <a href="https://wa.me/<?php echo htmlspecialchars($car['seller_whatsapp']); ?>" 
                               target="_blank" 
                               class="contact-option btn-primary">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                                WhatsApp: <?php echo htmlspecialchars($car['seller_whatsapp']); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($car['seller_email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($car['seller_email']); ?>" 
                               class="contact-option btn-secondary">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                Email: <?php echo htmlspecialchars($car['seller_email']); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($car['seller_phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($car['seller_phone']); ?>" 
                               class="contact-option btn-secondary">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                Phone: <?php echo htmlspecialchars($car['seller_phone']); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (empty($car['seller_whatsapp']) && empty($car['seller_email']) && empty($car['seller_phone'])): ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                Contact information not available for this seller.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modular JavaScript -->
    <script src="js/gallery.js"></script>
    <script src="js/feed-interactions.js"></script>
    <script>
        // Initialize gallery navigation
        const totalImages = <?php echo count($car['images']); ?>;
        if (typeof initGalleryNavigation === 'function') {
            initGalleryNavigation(totalImages);
        }
        
        // Handle back button navigation
        function goBack() {
            const fromSearch = <?php echo $fromSearch ? 'true' : 'false'; ?>;
            
            if (fromSearch) {
                // Return to search page with from=detail parameter
                window.location.href = 'search.php?from=detail';
            } else {
                // Default: return to feed
                window.location.href = 'index.php';
            }
        }
        
        // Make goBack available globally
        window.goBack = goBack;
        
        // Contact seller button
        document.getElementById('contactSellerBtn')?.addEventListener('click', function() {
            document.getElementById('contactModal').style.display = 'flex';
        });
        
        function closeContactModal() {
            document.getElementById('contactModal').style.display = 'none';
        }
        
        // Save button for detail page
        document.querySelector('.save-btn-detail')?.addEventListener('click', function() {
            const carId = this.getAttribute('data-car-id');
            const isSaved = this.classList.contains('saved');
            
            fetch('api/save_listing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    car_id: carId,
                    action: isSaved ? 'unsave' : 'save'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.action === 'saved') {
                        this.classList.add('saved');
                        this.querySelector('svg').setAttribute('fill', 'currentColor');
                    } else {
                        this.classList.remove('saved');
                        this.querySelector('svg').setAttribute('fill', 'none');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const contactModal = document.getElementById('contactModal');
            if (event.target === contactModal) {
                closeContactModal();
            }
        });
    </script>
    
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>

