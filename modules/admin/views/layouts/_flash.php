<?php

foreach (['success' => 'admin-flash--success', 'error' => 'admin-flash--error', 'warning' => 'admin-flash--warning'] as $key => $class) {
    if (Yii::$app->session->hasFlash($key)) {
        echo '<div class="admin-flash ' . $class . '">' . Yii::$app->session->getFlash($key) . '</div>';
    }
}

if (Yii::$app->session->hasFlash('dealerAccessCopy')) {
    echo $this->render('_dealer_access_flash', [
        'data' => Yii::$app->session->getFlash('dealerAccessCopy'),
    ]);
}
