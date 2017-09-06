<?php
require __DIR__ . '/api/Config.php';
require __DIR__ . '/api/Template.php';

use Directus\Util\ArrayUtils;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Directus\Database\TableGatewayFactory as TableFactory;
use StaticGenerator\Template;
use StaticGenerator\Config;

$app = \Directus\Application\Application::getInstance();

$templateStorageAdapter = new Filesystem( new Local( Config::getTemplateStoragePath() ) );

/**************************
 * GET                    *
 **************************/
$app->get('/templates/?', function () use ($app, $templateStorageAdapter) {    

    try {       
        $templates = Template::getAll($templateStorageAdapter);
        $data = [];
        
        if( $templates) {
            foreach($templates as $template) {
                $data[] = [              
                    'id' => $template->id,
                    'route' => $template->route,
                    'file' => $template->route,
                    'type' => $template->type,
                    'contents' => $template->contents,
                    'isPage' => $template->type == 'page',
                    'exists' => $template->exists(),
                ];
            }
        }

        return $app->response($data);
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
 * POST                   *
 **************************/
$app->post('/templates', function () use ($app, $templateStorageAdapter) {

    try {     
        // generate site
        if($app->request()->post('generate')) {
            
            $outputStorageAdapter = new Filesystem( new Local( Config::getOutputStoragePath() ) );
            $contents = $outputStorageAdapter->listContents();
            if($contents) {
                foreach($contents as $content) {
                    if(ArrayUtils::get($content, 'type') != 'dir') continue;
                    
                    $outputStorageAdapter->deleteDir(ArrayUtils::get($content, 'path'));
                }
            }
        
            $outputStorageAdapter->deleteDir('output');
            $templates = Template::getAll($templateStorageAdapter);  
            
            if( $templates) {
                foreach($templates as $template) {
                    
                    if($template->type != 'page') continue;
                    
                    $parsedTemplates = $template->parseTemplate();
                    
                    foreach($parsedTemplates as $parsedTemplate) {
                        $outputStorageAdapter->put(ArrayUtils::get($parsedTemplate, 'routePath'), ArrayUtils::get($parsedTemplate, 'contents'));
                    }
                }
            }
            
            return $app->response([
                'success' => true,
                'message' => 'Site generated.',
            ]);
        }  
        
        $template = new Template($templateStorageAdapter, [
            'filePath' => $app->request()->post('type') == 'include' ? $app->request()->post('route') : $app->request()->post('route') . '/index.html', 
            'type' => $app->request()->post('type'),
            'contents' => $app->request()->post('contents'),
        ]);
        
        if($template->exists()) {
            throw new Exception('Template already exists.');
        }
        
        $template->save();
    
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
$app->put('/templates/:id', function ($id = null) use ($app, $templateStorageAdapter) {

    try {     
        
        // if route has changed, delete old and create new
        if($app->request()->post('original_route') && $app->request()->post('route') && $app->request()->post('route') != $app->request()->post('original_route')) {   

            $newTemplate = new Template($templateStorageAdapter, [
                'filePath' => $app->request()->post('type') == 'include' ? $app->request()->post('route') : $app->request()->post('route') . '/index.html', 
                'type' => $app->request()->post('type'),
                'contents' => $app->request()->post('contents'),
            ]);
            
            $newTemplate->save();
            
            //$template->delete();
        }  
        
        else {

            $template = Template::getById($templateStorageAdapter, $id);
            $template->contents = $app->request()->post('contents');
            $template->save();
        }
    
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
$app->delete('/templates/:id', function ($id = null) use ($app, $templateStorageAdapter) { 

    try {
        
        $template = Template::getById($templateStorageAdapter, $id);
        $template->delete();
    
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

function du($c) {
    echo '<pre>';
    var_dump($c);
    echo '</pre>';
}


function dd($c) {
    du($c);
    die();
}
