<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gift Card Details | AirAsia Gift Cards</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <a href="card-list.php">AirAsia Gift Cards</a>
        <nav aria-label="Main navigation">
            <a href="card-list.php">Gift Card List</a>
            <a href="card-add.php">Add Gift Card</a>
            <a href="login.php">Sign Out</a>
        </nav>
    </header>
    <main class="page">
        <section class="panel">
            <h1>Gift Card Details</h1>
            <div class="gift-card-details">
                <section id="starbucks" class="gift-card-detail">
                    <h2>Starbucks Gift Card Details</h2>
                    <div class="details-layout">
                        <img src="images/starbucks-card.svg" alt="Starbucks gift card">
                        <dl class="card-details">
                            <div><dt>Card Name:</dt><dd>Starbucks</dd></div>
                            <div><dt>Card Type:</dt><dd>Food and Beverage</dd></div>
                            <div><dt>Card Value:</dt><dd>$10.00</dd></div>
                            <div><dt>Points Required:</dt><dd>1,500</dd></div>
                        </dl>
                    </div>
                    <a class="button" href="card-update.php#starbucks">Update Card</a>
                </section>
                <section id="amazon" class="gift-card-detail">
                    <h2>Amazon Gift Card Details</h2>
                    <div class="details-layout">
                        <img src="images/amazon-card.svg" alt="Amazon gift card">
                        <dl class="card-details">
                            <div><dt>Card Name:</dt><dd>Amazon</dd></div>
                            <div><dt>Card Type:</dt><dd>Shopping</dd></div>
                            <div><dt>Card Value:</dt><dd>$25.00</dd></div>
                            <div><dt>Points Required:</dt><dd>2,500</dd></div>
                        </dl>
                    </div>
                    <a class="button" href="card-update.php#amazon">Update Card</a>
                </section>
                <section id="target" class="gift-card-detail">
                    <h2>Target Gift Card Details</h2>
                    <div class="details-layout">
                        <img src="images/target-card.svg" alt="Target gift card">
                        <dl class="card-details">
                            <div><dt>Card Name:</dt><dd>Target</dd></div>
                            <div><dt>Card Type:</dt><dd>Shopping</dd></div>
                            <div><dt>Card Value:</dt><dd>$20.00</dd></div>
                            <div><dt>Points Required:</dt><dd>2,000</dd></div>
                        </dl>
                    </div>
                    <a class="button" href="card-update.php#target">Update Card</a>
                </section>
            </div>
            <div class="button-row">
                <a href="card-list.php">Back to Gift Card List</a>
            </div>
        </section>
    </main>
</body>
</html>
