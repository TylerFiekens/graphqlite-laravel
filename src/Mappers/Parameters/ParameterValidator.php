<?php

namespace TheCodingMachine\GraphQLite\Laravel\Mappers\Parameters;

use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Validation\Factory as ValidationFactory;
use TheCodingMachine\GraphQLite\Exceptions\GraphQLAggregateException;
use TheCodingMachine\GraphQLite\Laravel\Exceptions\ValidateException;
use TheCodingMachine\GraphQLite\Parameters\InputTypeParameterInterface;

class ParameterValidator implements InputTypeParameterInterface
{
    /**
     * @var InputTypeParameterInterface
     */
    private $parameter;

    /**
     * @var string
     */
    private $rules;

    /**
     * @var ValidationFactory
     */
    private $validationFactory;

    /**
     * @var string
     */
    private $parameterName;

    public function __construct(InputTypeParameterInterface $parameter, string $parameterName, string $rules, ValidationFactory $validationFactory)
    {
        $this->parameter = $parameter;
        $this->rules = $rules;
        $this->validationFactory = $validationFactory;
        $this->parameterName = $parameterName;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function resolve(?object $source, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $value = $this->parameter->resolve($source, $args, $context, $info);

        $validator = $this->validationFactory->make([$this->parameterName => $value], [$this->parameterName => $this->rules]);

        if ($validator->fails()) {
            $errorMessages = [];
            foreach ($validator->errors()->toArray() as $field => $errors) {
                foreach ($errors as $error) {
                    $errorMessages[] = ValidateException::create($error, $field);
                }
            }
            GraphQLAggregateException::throwExceptions($errorMessages);
        }

        return $value;
    }

    public function getType(): InputType&Type
    {
        return $this->parameter->getType();
    }

    public function hasDefaultValue(): bool
    {
        return $this->parameter->hasDefaultValue();
    }

    public function getDefaultValue(): mixed
    {
        return $this->parameter->getDefaultValue();
    }

    public function getName(): string
    {
        return $this->parameter->getName();
    }

    public function getDescription(): string
    {
        return $this->parameter->getDescription();
    }
}
