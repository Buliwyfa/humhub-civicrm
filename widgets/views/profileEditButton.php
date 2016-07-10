<?php

use yii\helpers\Html;
use yii\helpers\Url;

print Html::a(Yii::t("UserModule.widgets_views_profileEditButton", "<i class='fa fa-pencil'></i> Edit account"), Url::toRoute('/user/account/edit'), array('class' => 'btn btn-primary edit-account'));
