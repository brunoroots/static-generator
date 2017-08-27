<?php
require __DIR__ . '/api/Config.php';
require __DIR__ . '/api/Template.php';
require __DIR__ . '/api/Generator.php';

use Directus\Util\ArrayUtils;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Directus\Database\TableGatewayFactory as TableFactory;

$app = \Directus\Application\Application::getInstance();

$templateStorageAdapter = new Filesystem( new Local( \StaticGenerator\Config::getTemplateStoragePath() ) );
$template = new \StaticGenerator\Template($templateStorageAdapter);

/**************************
 * GET                    *
 **************************/
$app->get('/templates/?', function () use ($app, $template) {    




//     $inputStorageAdapter = new Filesystem( new Local( \StaticGenerator\Config::getTemplateStoragePath() ) );
//     $outputStorageAdapter = new Filesystem( new Local( \StaticGenerator\Config::getOutputStoragePath() ) );
//     $generator = new \StaticGenerator\Generator($inputStorageAdapter, $outputStorageAdapter);
//     $generator->generateSite('page');    
//     dd($staticGenerator->getTemplates());   
    return $app->response($template->getTemplates());
});

/**************************
 * POST                   *
 **************************/
$app->post('/templates', function () use ($app, $template) {

    try {     
        // generate site
        if($app->request()->post('generate')) {
            
            $inputStorageAdapter = new Filesystem( new Local( \StaticGenerator\Config::getTemplateStoragePath() ) );
            $outputStorageAdapter = new Filesystem( new Local( \StaticGenerator\Config::getOutputStoragePath() ) );
            $generator = new \StaticGenerator\Generator($inputStorageAdapter, $outputStorageAdapter);
            $generator->generateSite('page');
            
            return $app->response([
                'success' => true,
                'message' => 'Site generated.',
            ]);
        }  
        

        
        $template->saveTemplate([
            'route' => $app->request()->post('route'), 
            'type' => $app->request()->post('type'),
            'contents' => $app->request()->post('contents'),
        ]);
    
        return $app->response([
            'success' => true,
            'message' => 'Request successfully processed.',
        ]);
    }
    
    catch (Exception $e) {
    
        return $app->response([
            'success' => false,
            'error' => [
                'message' => $e->getMessage(),
            ],
        ])->status(400);        
    }
});

/**************************
 * PUT                    *
 **************************/
$app->put('/templates/:id', function ($id = null) use ($app, $template) {

    try {     
        // if route has changed, delete old and create new
        if($app->request()->post('original_route') && $app->request()->post('route') != $app->request()->post('original_route')) {   
        
            $template->deleteTemplate($id);      
            $id = null;      
        }        
        
        $template->saveTemplate([
            'id' => $id,
            'route' => $app->request()->post('route'), 
            'type' => $app->request()->post('type'),
            'contents' => $app->request()->post('contents'),
        ]);
    
        return $app->response([
            'success' => true,
            'message' => 'Request successfully processed.',
        ]);
    }
    
    catch (Exception $e) {
    
        return $app->response([
            'success' => false,
            'error' => [
                'message' => $e->getMessage(),
            ],
        ])->status(400);        
    }
});

/**************************
 * DELETE                 *
 **************************/
$app->delete('/templates/:id', function ($id = null) use ($app, $template) { 

    try {
        $template->deleteTemplate($id);
    
        return $app->response([
            'success' => true,
            'message' => 'Request successfully processed.',
        ]);
    }
    
    catch (Exception $e) {
    
        return $app->response([
            'success' => false,
            'error' => [
                'message' => $e->getMessage(),
            ],
        ])->status(400);    
    }
});


function dd($c) {
    echo '<pre>';
    var_dump($c);
    echo '</pre>';
    die();
}
