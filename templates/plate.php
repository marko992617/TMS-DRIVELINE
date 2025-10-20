<h1 class="page-title">Plate vozača</h1>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" class="form-inline mb-4">
    <div class="form-group">
        <label for="driver_id">Vozač:</label>
        <select id="driver_id" name="driver_id" required class="form-control">
            <option value="">-- Izaberite vozača --</option>
            <?php foreach ($drivers as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($driverId == $d['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="from_date">Od:</label>
        <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($from) ?>"
               required class="form-control">
    </div>

    <div class="form-group">
        <label for="to_date">Do:</label>
        <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($to) ?>"
               required class="form-control">
    </div>

    <button type="submit" class="btn btn-primary">Povuci ture</button>
</form>

<?php if ($rows): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Datum</th>
                <th>Prihod</th>
                <th>Dnevnica</th>
                <th>Ispravka</th>
                <th>Uplata na račun</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['revenue']) ?></td>
                    <td>
                        <input type="number" step="0.01"
                               name="adjust[<?= $row['date'] ?>][daily_allowance]"
                               value="<?= $row['daily_allowance'] ?? '0.00' ?>"
                               class="form-control"/>
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="adjust[<?= $row['date'] ?>][adjustment]"
                               value="<?= $row['adjustment_amount'] ?? '0.00' ?>"
                               class="form-control"/>
                    </td>
                    <td>
                        <input type="number" step="0.01"
                               name="adjust[<?= $row['date'] ?>][bank_payment]"
                               value="<?= $row['bank_payment'] ?? '0.00' ?>"
                               class="form-control"/>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mb-4">
        <button type="submit" name="save_adjustments" class="btn btn-success">Sačuvaj izmene</button>
        <button type="submit" formaction="/generate_payroll_pdf.php" name="generate_pdf"
                class="btn btn-secondary">Generiši PDF</button>
    </div>
<?php endif; ?>