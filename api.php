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
        
        $directoryTree = [];
        if( $templates) {
            foreach($templates as $template) {
               
                $directoryTree = array_merge_recursive($directoryTree, makeTree(explode('/', $template->filePath . ':::' . $template->id)));
                $filePathParts = explode('/', $template->filePath);
                
                $data[] = [              
                    'id' => $template->id,
                    'route' => $template->route,
                    'file' => $template->filePath,
                    'fileName' => array_pop($filePathParts),
                    'contents' => $template->contents,
                    'exists' => $template->exists(),
                    'hasDirectoryTree' => false,
                ];
            }
        }

        $data[] = [
            'hasDirectoryTree' => true,
            'directoryTree' => toUL($directoryTree),
        ];

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
                   
        /**
         * Generate site
         */
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
        
        /**
         * Create new template
         */
        // validation
        if( ! $app->request()->post('filePath')) {
            throw new Exception('Please enter a file path.');
        }
        
        if ( substr($app->request()->post('filePath'), -5) != '.html') {
            throw new Exception('The file extension must be `.html`.  Ex - `' . $app->request()->post('filePath') . '.html`.');
        }
        
        // instantiate
        $template = new Template($templateStorageAdapter, [
            'filePath' => $app->request()->post('filePath'), 
            'contents' => $app->request()->post('contents'),
        ]);
        
        // check and save
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
        
        // validation
        if( ! $app->request()->post('filePath')) {
            throw new Exception('Please enter a file path.');
        }
        
        if ( substr($app->request()->post('filePath'), -5) != '.html') {
            throw new Exception('The file extension must be `.html`.  Ex - `' . $app->request()->post('filePath') . '.html`.');
        }
        
        // instantiate template
        $template = Template::getById($templateStorageAdapter, $id);
        
        // save template
        $filePathChanged = false;

        if( $app->request()->post('filePath') && $app->request()->post('filePath') != $template->filePath) {
            $filePathChanged = true;
        }
        
        if($filePathChanged) {
            $template->filePath = $app->request()->post('filePath');
        }
        
        $template->contents = $app->request()->post('contents');
        $template->save();
        
        // if file path is different, delete the old template
        if( $filePathChanged) {
            $template = Template::getById($templateStorageAdapter, $id);
            $template->delete();
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

/**
 * Converts filepath flat array to multi-dimensional array
 * 
 * @param array $arr
 * @return multitype:unknown |unknown
 */
function makeTree($arr) 
{
    $part = array_shift($arr);
    
    if( ! $arr) {
        return [$part];
    }
    
    $tree[$part] = makeTree($arr);
    
    return $tree;
}

/**
 * Converts multi-dimensional array to htmls list output
 * 
 * @param string $data
 * @return string
 */
function toUL($data = false)
{
    $response = '<ul>';
    if (false !== $data) {
        foreach ($data as $key => $val) {
            
            $response .= '<li>';
            
            if (! is_array($val)) {
                list($fileName, $fileId) = explode(':::', $val);
                $response .= '<a href="#" data-id="' . $fileId . '" class="file">' . $fileName . '</a>'
                          .  '<i data-id="' . $fileId . '" class="material-icons delete-file">delete</i>'
                          .  '<i data-id="' . $fileId . '" class="material-icons edit-file">edit</i>';
            } 
            
            else {
                $response .= $key . ' ' . toUL($val);
            }
            $response .= '</li>';
        }
    }
    $response .= '</ul>';
    return $response;
}
