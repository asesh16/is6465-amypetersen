<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Gift Card | AirAsia Gift Cards</title>
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
        <section class="panel form-panel">
            <h1>Add Gift Card</h1>
            <form action="card-list.php" method="get">
                <div class="form-field">
                    <label for="card-name">Card Name</label>
                    <input id="card-name" name="card-name" type="text" required>
                </div>
                <div class="form-field">
                    <label for="card-type">Card Type</label>
                    <select id="card-type" name="card-type" required>
                        <option value="" selected disabled>Select a card type</option>
                        <option value="food-beverage">Food and Beverage</option>
                        <option value="shopping">Shopping</option>
                        <option value="entertainment">Entertainment</option>
                        <option value="travel">Travel</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="card-value">Card Value</label>
                    <div class="input-with-prefix">
                        <span aria-hidden="true">$</span>
                        <input id="card-value" name="card-value" type="number" min="0.01" step="0.01" placeholder="10.00" required>
                    </div>
                </div>
                <div class="form-field">
                    <label for="points-required">Points Required</label>
                    <input id="points-required" name="points-required" type="number" min="1" step="1" required>
                </div>
                <div class="button-row">
                    <button type="submit">Submit</button>
                    <a href="card-list.php">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
