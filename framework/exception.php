<?php
$whoops = new \Whoops\Run;
$handler = new \Whoops\Handler\PrettyPageHandler;
// Set the title of the error page:
$handler->setPageTitle("Whoops! There was a problem.");

// Add a custom table to the layout:
// $handler->addDataTable('Ice-cream I like', [
// 	'Chocolate' => 'yes',
// 	'Coffee & chocolate' => 'a lot',
// 	'Strawberry & chocolate' => 'it\'s alright',
// 	'Vanilla' => 'ew',
// ]);

$handler->setApplicationPaths([__FILE__]);
$handler->addDataTableCallback('Trace', function(\Whoops\Exception\Inspector $inspector) {
	$data = array();
	$exception = $inspector->getException();
	if ($exception instanceof SomeSpecificException) {
			$data['Important exception data'] = $exception->getSomeSpecificData();
	}
	$data['Class'] = get_class($exception);
	$data['Code'] = $exception->getCode();
	$data['Line'] = $exception->getLine();
	$data['File'] = $exception->getFile();
	// $data['trace'] = $exception->getTraceAsString();
	return $data;
});

$whoops->pushHandler($handler);

// Example: tag all frames inside a function with their function name
$whoops->pushHandler(function ($exception, $inspector, $run) {
	$inspector->getFrames()->map(function ($frame) {
			if ($function = $frame->getFunction()) {
					$frame->addComment("This frame is within function '$function'", 'cpt-obvious');
			}
			return $frame;
	});
});

// Add a special handler to deal with AJAX requests with an
// equally-informative JSON response. Since this handler is
// first in the stack, it will be executed before the error
// page handler, and will have a chance to decide if anything
// needs to be done.
if (\Whoops\Util\Misc::isAjaxRequest()) {
  $whoops->pushHandler(new \Whoops\Handler\JsonResponseHandler);
}
$whoops->register();