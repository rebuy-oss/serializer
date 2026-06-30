<?php

declare(strict_types=1);

namespace Liip\Serializer\Template;

use Liip\Serializer\Path\ModelPath;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class Serialization
{
    private const TMPL_FUNCTION = <<<'EOT'
<?php

function {{functionName}}({{className}} $model, bool $useStdClass = true)
{
    $emptyHashmap = $useStdClass ? new \stdClass() : [];
    $emptyObject = $useStdClass ? new \stdClass() : [];

    {{code}}

    return $jsonData;
}

EOT;

    private const TMPL_CLASS = <<<'EOT'
{% if initialValues %}
{{target}} = [
{%- for pair in initialValues ~%}
        {% if pair.splat is defined and pair.splat is not empty %}...({{ pair.splat }}){% else %}{{ pair.key }} => {{ pair.value }}{% endif %},
{%- endfor ~%}
    ];
{% else -%}
{{target}} = [];
{% endif -%}
{{code}}
{% if withEmptyObject is defined and withEmptyObject %}
if ([] === {{target}}) {
    {{target}} = $emptyObject;
}
{% endif %}

EOT;

    private const TMPL_CONDITIONAL = <<<'EOT'
if (null !== {{condition}}) {
    {{code}}
}
{%- if elseCode -%}
else {
    {{elseCode}}
}
{% endif %}


EOT;

    private const TMPL_INSTANCE_OF_CONDITIONAL = <<<'EOT'
if ({{propertyAccessor}} instanceof {{class}}) {
    {{code}}
}

EOT;

    private const TMPL_PRIMITIVE_CONDITIONAL = <<<'EOT'
if (\Liip\Serializer\SerializerGenerator::isPrimitive({{propertyAccessor}})) {
    {{code}}
}
EOT;

    private const TMPL_ARRAY_CONDITIONAL = <<<'EOT'
if (\is_array({{propertyAccessor}})) {
    {{code}}
}
EOT;

    private const TMPL_ASSIGN = <<<'EOT'
{{target}} = {{propertyAccessor}};
EOT;

    private const TMPL_ARRAY_ASSIGN = <<<'EOT'
{{target}} = \is_array({{propertyAccessor}}) ? {{propertyAccessor}} : \iterator_to_array({{propertyAccessor}});
EOT;

    private const TMPL_HASHMAP = <<<'EOT'
if (0 === \count({{arrayVariable}})) {
    {{target}} = $emptyHashmap;
} else {
    {{target}} = \array_is_list({{arrayVariable}}) ? new \ArrayObject({{arrayVariable}}) : {{arrayVariable}};
}
EOT;

    private const TMPL_HASHMAP_EMPTY = <<<'EOT'
{{target}} = $emptyHashmap;

EOT;

    private const TMPL_LOOP_ARRAY = <<<'EOT'
{{target}} = [];
foreach ({{propertyAccessor}} as {{indexVariable}} => {{valueVariable}}) {
    {{code}}
}

EOT;

    private const TMPL_LOOP_ARRAY_EMPTY = <<<'EOT'
{{target}} = [];

EOT;

    private const TMPL_GETTER = '{{modelPath}}->{{method}}()';

    private const TMPL_DATETIME = '{{propertyPath}}{{ nullable ? "?" : "" }}->format({{format}})';

    private const TMPL_TEMP_VAR = '{{name}} = {{value}}';

    private Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader(), ['autoescape' => false]);
    }

    public function renderFunction(string $name, string $className, string $code): string
    {
        return $this->render(self::TMPL_FUNCTION, [
            'functionName' => $name,
            'className' => $className,
            'code' => $code,
        ]);
    }

    /**
     * @phpstan-param array<array{
     *     "key": string|null,
     *     "value": string,
     *     "splat"?: string|null,
     * }> $initialValues
     */
    public function renderClass(string|ModelPath $target, string $code, array $initialValues = [], bool $withEmptyObject = true): string
    {
        return $this->render(self::TMPL_CLASS, [
            'target' => $target,
            'code' => $code,
            'initialValues' => $initialValues,
            'withEmptyObject' => $withEmptyObject,
        ]);
    }

    public function renderConditional(string $condition, string $code, ?string $elseCode = null): string
    {
        return $this->render(self::TMPL_CONDITIONAL, [
            'condition' => $condition,
            'code' => $code,
            'elseCode' => $elseCode,
        ]);
    }

    public function renderInstanceOfConditional(string|ModelPath $propertyAccessor, string $class, string $code): string
    {
        return $this->render(self::TMPL_INSTANCE_OF_CONDITIONAL, [
            'propertyAccessor' => $propertyAccessor,
            'class' => $class,
            'code' => $code,
        ]);
    }

    public function renderPrimitiveConditional(string|ModelPath $propertyAccessor, string $code): string
    {
        return $this->render(self::TMPL_PRIMITIVE_CONDITIONAL, [
            'propertyAccessor' => $propertyAccessor,
            'code' => $code,
        ]);
    }

    public function renderArrayConditional(string|ModelPath $propertyAccessor, string $code): string
    {
        return $this->render(self::TMPL_ARRAY_CONDITIONAL, [
            'propertyAccessor' => $propertyAccessor,
            'code' => $code,
        ]);
    }

    public function renderAssign(string|ModelPath $target, string $propertyAccessor): string
    {
        return $this->render(self::TMPL_ASSIGN, [
            'target' => $target,
            'propertyAccessor' => $propertyAccessor,
        ]);
    }

    public function renderArrayAssign(string|ModelPath $target, string $propertyAccessor): string
    {
        return $this->render(self::TMPL_ARRAY_ASSIGN, [
            'target' => $target,
            'propertyAccessor' => $propertyAccessor,
        ]);
    }

    public function renderLoopArray(string|ModelPath $target, string|ModelPath $propertyAccessor, string|ModelPath $indexVariable, string|ModelPath $valueVariable, string $code): string
    {
        return $this->render(self::TMPL_LOOP_ARRAY, [
            'target' => $target,
            'propertyAccessor' => $propertyAccessor,
            'indexVariable' => $indexVariable,
            'valueVariable' => $valueVariable,
            'code' => $code,
        ]);
    }

    public function renderLoopArrayEmpty(string|ModelPath $target): string
    {
        return $this->render(self::TMPL_LOOP_ARRAY_EMPTY, [
            'target' => $target,
        ]);
    }

    public function renderHashmap(string|ModelPath $target, string|ModelPath $arrayVariable): string
    {
        return $this->render(self::TMPL_HASHMAP, [
            'target' => $target,
            'arrayVariable' => $arrayVariable,
        ]);
    }

    public function renderLoopHashmapEmpty(string|ModelPath $target): string
    {
        return $this->render(self::TMPL_HASHMAP_EMPTY, [
            'target' => $target,
        ]);
    }

    public function renderGetter(string $modelPath, string $method): string
    {
        return $this->render(self::TMPL_GETTER, [
            'modelPath' => $modelPath,
            'method' => $method,
        ]);
    }

    public function renderDateTime(string $propertyPath, string $format, bool $nullable = false): string
    {
        return $this->render(self::TMPL_DATETIME, [
            'propertyPath' => $propertyPath,
            'format' => var_export($format, true),
            'nullable' => $nullable,
        ]);
    }

    public function renderTempVariable(string|ModelPath $name, string $value): string
    {
        return $this->render(self::TMPL_TEMP_VAR, [
            'name' => $name,
            'value' => $value,
        ]);
    }

    public function renderConditionalUsingTempVariable(string|ModelPath $tempVariable, string|ModelPath $propertyAccessor, string $code): string
    {
        return $this->render(self::TMPL_CONDITIONAL, [
            'condition' => $this->renderTempVariable("{$tempVariable}", "{$propertyAccessor}"),
            'code' => $code,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $template, array $parameters): string
    {
        $tmpl = $this->twig->createTemplate($template);

        return $tmpl->render($parameters);
    }

    public static function varJsonPath(): ModelPath
    {
        return new ModelPath('jsonData');
    }

    public static function varModel(): ModelPath
    {
        return new ModelPath('model');
    }
}
