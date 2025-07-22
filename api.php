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

$app->get('/plugin/healthchecks/settings/services', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->_pluginGetServicesSettings());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/settings/services/{id}', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->_pluginGetServicesSettings($args['id']));
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/enabled_services', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-READ'] ?? 'ACL-READ')) {
		$data = $request->getQueryParams();
		$sort = $data['sort'] ?? 'name';
		$order = $data['order'] ?? 'asc';
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->getEnabledServices($sort,$order));
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/services', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$data = $request->getQueryParams();
		$sort = $data['sort'] ?? 'name';
		$order = $data['order'] ?? 'asc';
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->getServices($sort,$order));
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/services/{id}', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->getServiceById($args['id']));
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/services/{id}/history', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponseData($healthChecksPlugin->getServiceHistory($args['id']));
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->post('/plugin/healthchecks/services', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$data = $healthChecksPlugin->api->getAPIRequestData($request);
		if (!isset($data['name']) || empty($data['name'])) {
			$healthChecksPlugin->api->setAPIResponse('error', 'Service name is required');
		} elseif (!isset($data['host']) || empty($data['host'])) {
			$healthChecksPlugin->api->setAPIResponse('error', 'Service host is required');
		} else {
			$healthChecksPlugin->createService($data);
		}
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->patch('/plugin/healthchecks/services/{id}', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$data = $healthChecksPlugin->api->getAPIRequestData($request);
		$healthChecksPlugin->updateService($args['id'],$data);
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

$app->get('/plugin/healthchecks/check_all', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		$healthChecksPlugin->api->setAPIResponse($healthChecksPlugin->checkAll());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/healthchecks/check/{id}', function ($request, $response, $args) {
	$healthChecksPlugin = new healthChecksPlugin();
	if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-WRITE'] ?? 'ACL-WRITE')) {
		if (isset($args['id'])) {
			$Result = $healthChecksPlugin->check($args['id']);
			if (isset($Result['status']) && $Result['status'] == 'error') {
				$healthChecksPlugin->api->setAPIResponse('error', $Result['message']);
			} else {
				$healthChecksPlugin->api->setAPIResponseData($Result);
			}
		} else {
			$healthChecksPlugin->api->setAPIResponse('error', 'Service ID is required');
		}
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});