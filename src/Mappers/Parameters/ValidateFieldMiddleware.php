<?php

declare(strict_types=1);

namespace TheCodingMachine\GraphQLite\Laravel\Mappers\Parameters;

use Illuminate\Validation\Factory as ValidationFactory;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\Type;
use ReflectionParameter;
use TheCodingMachine\GraphQLite\Annotations\ParameterAnnotations;
use TheCodingMachine\GraphQLite\Laravel\Annotations\Validate;
use TheCodingMachine\GraphQLite\Mappers\Parameters\ParameterHandlerInterface;
use TheCodingMachine\GraphQLite\Mappers\Parameters\ParameterMiddlewareInterface;
use TheCodingMachine\GraphQLite\Parameters\InputTypeParameterInterface;
use TheCodingMachine\GraphQLite\Parameters\ParameterInterface;

use function array_map;
use function implode;

/**
 * A parameter middleware that reads "Validate" annotations.
 */
class ValidateFieldMiddleware implements ParameterMiddlewareInterface
{
    /**
     * @var ValidationFactory
     */
    private $validationFactory;

    public function __construct(ValidationFactory $validationFactory)
    {
        $this->validationFactory = $validationFactory;
    }

    public function mapParameter(ReflectionParameter $refParameter, DocBlock $docBlock, ?Type $paramTagType, ParameterAnnotations $parameterAnnotations, ParameterHandlerInterface $next): ParameterInterface
    {
        /** @var Validate[] $validateAnnotations */
        $validateAnnotations = $parameterAnnotations->getAnnotationsByType(Validate::class);

        $parameter = $next->mapParameter($refParameter, $docBlock, $paramTagType, $parameterAnnotations);

        if (empty($validateAnnotations)) {
            return $parameter;
        }

        if (! $parameter instanceof InputTypeParameterInterface) {
            throw InvalidValidateAnnotationException::canOnlyValidateInputType($refParameter);
        }

        // Let's wrap the ParameterInterface into a ParameterValidator.
        $rules = array_map(static function (Validate $validateAnnotation): string {
            return $validateAnnotation->getRule();
        }, $validateAnnotations);

        return new ParameterValidator($parameter, $refParameter->getName(), implode('|', $rules), $this->validationFactory);
    }
}
