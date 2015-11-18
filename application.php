<?php

use Silex\Application;
use Silex\Application\TwigTrait;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// because we can't use PHP 7.0's anonymous classes yet...
if (!class_exists('App')) {
    class App extends Application {
        use TwigTrait;
    }
}

$app = new App();

$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__.'/templates',
]);

$app->register(new Silex\Provider\DoctrineServiceProvider(), ['db.options' => [
    'driver' => 'pdo_sqlite',
    'path' => __DIR__.'/app.db',
]]);

$app->get('/', function () use ($app) {
    return $app->render('home.html.twig');
});

$app->get('/tidus_laugh.{ext}', function ($ext) use ($app) {
    $sth = $app['db']->prepare('SELECT mime_type FROM extensions WHERE extension = ?');
    $sth->bindValue(1, $ext, \PDO::PARAM_STR);
    $sth->execute();

    $mimeType = $sth->fetchColumn();

    if (!is_string($mimeType)) {
        $app->abort(404, 'No such extension');
    }

    $filename = __DIR__."/repository/tidus_laugh.$ext";

    $expiresDate = new \DateTime();
    $expiresDate->modify('+1 day');

    $response = new BinaryFileResponse($filename);
    $response->headers->set('Content-Type', $mimeType);
    $response->setPublic();
    $response->setExpires($expiresDate);

    return $response;
})->assert('ext', '^[0-9a-z]+$');

$app->get('/manage', function () use ($app) {
    $extensions = $app['db']->fetchAll('SELECT * FROM extensions');

    return $app->render('manage.html.twig', ['extensions' => $extensions]);
});

$app->post('/new', function (Request $request) use ($app) {
    $extension = $request->request->get('extension');
    $mimeType = $request->request->get('mime_type');
    $password = $request->request->get('password');

    foreach ([$extension, $mimeType, $password] as $input) {
        if (!is_string($input) || !strlen($input)) {
            $app->abort(403, 'Bad input');
        }
    }

    $extension = strtolower(preg_replace('/^[.]+/', '', $extension));
    $extension = preg_replace('/[.]+/', '.', $extension);

    if (!preg_match('/[A-Za-z0-9.]/', $extension)) {
        $app->abort(500, 'Invalid filename extension');
    }

    $authorized = password_verify($password, $app['password']);

    if (!$authorized) {
        $app->abort(403, "You aren't allowed to be here, fucko");
    }

    $file = $request->files->get('file');

    if (!$file instanceof UploadedFile || !$file->isValid()) {
        $app->abort(500, 'Either there was no file or the upload failed');
    }

    $app['db']->beginTransaction();

    $sth = $app['db']->prepare('INSERT INTO extensions (extension, mime_type) VALUES (?, ?)');
    $sth->bindValue(1, $extension);
    $sth->bindValue(2, $mimeType);
    $sth->execute();

    $file->move(__DIR__.'/repository', 'tidus_laugh.'.$extension);

    $app['db']->commit();

    return $app->redirect('/manage', 303);
});

$app->get('/repository.json', function () use ($app) {
    $all = $app['db']->fetchAll('SELECT * FROM extensions');

    $response = new JsonResponse(['extensions' => $all]);

    $response->setEncodingOptions(
        $response->getEncodingOptions() | JSON_PRETTY_PRINT
    );

    return $response;
});
