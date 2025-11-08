<?php
require_once('../../includes/db.php');


$query = "SELECT crop_type, forecast_date, forecast_value, model_type, accuracy_score, created_at 
          FROM forecast_results 
          ORDER BY created_at DESC";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo '<table class="table table-bordered table-striped">';
    echo '<thead>
            <tr>
                <th>Crop Type</th>
                <th>Forecast Month</th>
                <th>Predicted KG</th>
                <th>Model</th>
                <th>AIC</th>
                <th>Generated</th>
            </tr>
          </thead><tbody>';

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['crop_type']}</td>
                <td>{$row['forecast_month']}</td>
                <td>{$row['forecast_value']}</td>
                <td>{$row['model_type']}</td>
                <td>{$row['aic_score']}</td>
                <td>{$row['created_at']}</td>
              </tr>";
    }

    echo '</tbody></table>';
} else {
    echo "<p>No forecast results yet. Click Run Forecast.</p>";
}
