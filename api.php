<?php
// **
// USED TO DEFINE CUSTOM API ROUTES
// **
$app->get('/plugin/healthchecks/settings', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->_pluginGetSettings());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/services', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->getServices());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->post('/plugin/healthchecks/services', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$data = $phpef->api->getAPIRequestData($request);
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->createService($data));
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->delete('/plugin/healthchecks/services/{id}', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->deleteService($args['id']));
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/status', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-READ'] ?? 'ACL-READ')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->checkAll());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});