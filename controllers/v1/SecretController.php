<?php

namespace app\controllers\v1;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\filters\ContentNegotiator;
use app\models\Secret;

class SecretController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['POST'],
                    'view' => ['GET'],
                ],
            ],
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                    'application/xml'  => Response::FORMAT_XML,
                ],
            ],
        ];
    }

    public function actionCreate()
    {
        $request = Yii::$app->request;
        $secretText = $request->post('secret');
        $expireAfterViews = (int)$request->post('expireAfterViews');
        $expireAfter = (int)$request->post('expireAfter');

        if ($secretText === null || $expireAfterViews < 1 || $expireAfter < 0) {
            Yii::$app->response->statusCode = 405;
            return [
                'error' => 'Invalid input. Required: secret (string), expireAfterViews (>0), expireAfter (>=0 minutes).',
            ];
        }

        $model = Secret::createFromInput($secretText, $expireAfterViews, $expireAfter);
        if (!$model->save()) {
            Yii::$app->response->statusCode = 405;
            return [
                'error' => 'Validation failed',
                'details' => $model->getErrors(),
            ];
        }

        return $model;
    }

    public function actionView($hash)
    {
        $model = Secret::findOne(['hash' => $hash]);
        if (!$model || !$model->canBeViewed()) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Secret not found'];
        }

        if (!$model->consumeOneView()) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Secret not found'];
        }

        return $model;
    }
}
