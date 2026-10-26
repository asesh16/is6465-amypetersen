<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update Gift Card | AirAsia Gift Cards</title>
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
            <h1>Update Gift Card</h1>
            <div class="card-update-forms">
                <form id="starbucks" action="card-list.php" method="get">
                    <div class="form-field">
                        <label for="starbucks-name">Card Name</label>
                        <input id="starbucks-name" name="card-name" type="text" value="Starbucks" required>
                    </div>
                    <div class="form-field">
                        <label for="starbucks-type">Card Type</label>
                        <select id="starbucks-type" name="card-type" required>
                            <option value="food-beverage" selected>Food and Beverage</option>
                            <option value="shopping">Shopping</option>
                            <option value="entertainment">Entertainment</option>
                            <option value="travel">Travel</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="starbucks-value">Card Value</label>
                        <div class="input-with-prefix">
                            <span aria-hidden="true">$</span>
                            <input id="starbucks-value" name="card-value" type="number" min="0.01" step="0.01" value="10.00" required>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="starbucks-points">Points Required</label>
                        <input id="starbucks-points" name="points-required" type="number" min="1" step="1" value="1500" required>
                    </div>
                    <div class="button-row">
                        <button type="submit">Submit</button>
                        <a href="card-details.php#starbucks">Cancel</a>
                    </div>
                </form>
                <form id="amazon" action="card-list.php" method="get">
                    <div class="form-field">
                        <label for="amazon-name">Card Name</label>
                        <input id="amazon-name" name="card-name" type="text" value="Amazon" required>
                    </div>
                    <div class="form-field">
                        <label for="amazon-type">Card Type</label>
                        <select id="amazon-type" name="card-type" required>
                            <option value="food-beverage">Food and Beverage</option>
                            <option value="shopping" selected>Shopping</option>
                            <option value="entertainment">Entertainment</option>
                            <option value="travel">Travel</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="amazon-value">Card Value</label>
                        <div class="input-with-prefix">
                            <span aria-hidden="true">$</span>
                            <input id="amazon-value" name="card-value" type="number" min="0.01" step="0.01" value="25.00" required>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="amazon-points">Points Required</label>
                        <input id="amazon-points" name="points-required" type="number" min="1" step="1" value="2500" required>
                    </div>
                    <div class="button-row">
                        <button type="submit">Submit</button>
                        <a href="card-details.php#amazon">Cancel</a>
                    </div>
                </form>
                <form id="target" action="card-list.php" method="get">
                    <div class="form-field">
                        <label for="target-name">Card Name</label>
                        <input id="target-name" name="card-name" type="text" value="Target" required>
                    </div>
                    <div class="form-field">
                        <label for="target-type">Card Type</label>
                        <select id="target-type" name="card-type" required>
                            <option value="food-beverage">Food and Beverage</option>
                            <option value="shopping" selected>Shopping</option>
                            <option value="entertainment">Entertainment</option>
                            <option value="travel">Travel</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="target-value">Card Value</label>
                        <div class="input-with-prefix">
                            <span aria-hidden="true">$</span>
                            <input id="target-value" name="card-value" type="number" min="0.01" step="0.01" value="20.00" required>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="target-points">Points Required</label>
                        <input id="target-points" name="points-required" type="number" min="1" step="1" value="2000" required>
                    </div>
                    <div class="button-row">
                        <button type="submit">Submit</button>
                        <a href="card-details.php#target">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
