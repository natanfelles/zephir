<?php

/*
 +--------------------------------------------------------------------------+
 | Zephir                                                                   |
 | Copyright (c) 2013-present Zephir Team (https://zephir-lang.com/)        |
 |                                                                          |
 | This source file is subject the MIT license, that is bundled with this   |
 | package in the file LICENSE, and is available through the world-wide-web |
 | at the following url: http://zephir-lang.com/license.html                |
 +--------------------------------------------------------------------------+
 | Authors: Rack Lin <racklin@gmail.com>                                    |
 +--------------------------------------------------------------------------+
*/

namespace Zephir\Optimizers\FunctionCall;

use Zephir\Call;
use Zephir\CompilationContext;
use Zephir\Compiler\CompilerException;
use Zephir\CompiledExpression;
use Zephir\Optimizers\OptimizerAbstract;

/**
 * JsonEncodeOptimizer
 *
 * Optimizes calls to 'json_encode' using internal function
 */
class JsonEncodeOptimizer extends OptimizerAbstract
{
    /**
     * @param array $expression
     * @param Call $call
     * @param CompilationContext $context
     * @return bool|CompiledExpression|mixed
     */
    public function optimize(array $expression, Call $call, CompilationContext $context)
    {
        if (!isset($expression['parameters'])) {
            return false;
        }

        /**
         * Process the expected symbol to be returned
         */
        $call->processExpectedReturn($context);

        $symbolVariable = $call->getSymbolVariable(true, $context);
        if (!$symbolVariable->isVariable()) {
            throw new CompilerException("Returned values by functions can only be assigned to variant variables", $expression);
        }

        $context->headersManager->add('kernel/string');

        $resolvedParams = $call->getReadOnlyResolvedParams($expression['parameters'], $context, $expression);

        /**
         * Process encode options
         */
        if (count($resolvedParams) >= 2) {
            $context->headersManager->add('kernel/operators');
            $options = 'zephir_get_intval(' . $resolvedParams[1] . ') ';
        } else {
            $options = '0 ';
        }

        if ($call->mustInitSymbolVariable()) {
            $symbolVariable->initVariant($context);
        }

        $symbol = $context->backend->getVariableCode($symbolVariable);
        if ($context->backend->isZE3()) {
            $context->codePrinter->output('zephir_json_encode(' . $symbol . ', ' . $resolvedParams[0] . ', '. $options .');');
        } else {
            $context->codePrinter->output('zephir_json_encode(' . $symbol . ', &(' . $symbol . '), ' . $resolvedParams[0] . ', '. $options .' TSRMLS_CC);');
        }
        return new CompiledExpression('variable', $symbolVariable->getRealName(), $expression);
    }
}
