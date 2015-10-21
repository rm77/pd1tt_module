<?
require 'Slim/Slim.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim(array('debug'=> true,'log.level' => \Slim\Log::DEBUG));
require_once ('./start.php');
$app->global = $GLOBAL;

$app->token_extractor_instance = new TokenExtractor($app->global['lms_id'],$app->global['lms_desc'],$app->global['lms_secret'],$app->global['lms_url']);



$app->get('/test',function () use ($app){
	echo json_encode(array('version'=>3));
	exit(0);
});

$app->post('/lmsg',function() use ($app){
            $token = $app->request->post('token');
            if (strlen($token)>0)
                serve($app,$token);
            else
                $app->notFound();
});

$app->group('/lms', function () use ($app) {
    $app->post('/gate', function () use ($app) {
            $token = $app->request->post('token');
            if (strlen($token)>0)
                serve($app,$token);
            else
                $app->notFound();
    });

});

$app->run();
?>
