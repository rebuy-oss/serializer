<?php

declare(strict_types=1);

namespace Liip\Serializer\Template;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class Deserialization
{
    private const PRIMITIVE_CHECKS = [
        'null' => '\is_null({{value}})',
        'array' => '\is_array({{value}})',
        'int' => '(string) (int) {{value}} === (string) {{value}}',
        'float' => '(string) (float) {{value}} === (string) {{value}}',
        'bool' => '!is_array({{value}}) && (string) (bool) {{value}} === (string) {{value}}',
        'true' => 'true === {{value}}',
        'false' => 'false === {{value}}',
        'string' => '!\is_array({{value}}) && !\is_object({{value}})',
    ];

    private const PRIMITIVE_CASTS = [
        'array' => '{{value}}',
        'int' => '(int) {{value}}',
        'float' => '(float) {{value}}',
        'bool' => '(bool) {{value}}',
        'string' => '(string) {{value}}',
    ];

    private const TMPL_FUNCTION = <<<'EOT'
<?php

function {{functionName}}(array {{jsonPath}}): {{className}}
{
    {{code}}

    return $model;
}

EOT;

    private const TMPL_CLASS = <<<'EOT'
{{initArgumentsCode}}
{{modelPath}} = new {{className}}({{arguments|join(', ')}});
{{code}}

EOT;

    private const TMPL_ARGUMENT = <<<'EOT'
{{variableName}} = {{default}};
{%- if code is not null ~%}
{{code}}
{%- endif -%}
EOT;

    private const TMPL_POST_METHOD = <<<'EOT'
{{modelPath}}->{{method}}();

EOT;

    private const TMPL_CONDITIONAL = <<<'EOT'
if (isset({{data}})) {
    {{code}}
}

EOT;

    private const TMPL_DISCRIMINATOR_CONDITIONAL = <<<'EOT'
if ({{jsonPath}} === '{{typeValue}}') {
    {{code}}
}

EOT;

    private const TMPL_PRIMITIVE_CONDITIONAL = <<<'EOT'
if ({{typeConditional}}) {
    {{code}}
} {% if withElseBlock %} else {% endif %}

EOT;

    private const TMPL_KEY_EXISTS_CONDITIONAL = <<<'EOT'
if (\array_key_exists({{index}}, {{data}})) {
    {{code}}
} {% if elseCode is not null %} else {
    {{ elseCode }}
}{% endif %}


EOT;

    private const TMPL_IS_NULL_CONDITIONAL = <<<'EOT'
if (null !== {{jsonPath}}) {
    {{code}}
} {% if elseCode is not null %} else {
    {{ elseCode }}
}{% endif %}


EOT;

    private const TMPL_ASSIGN_JSON_DATA_TO_FIELD = <<<'EOT'
{{modelPath}} = {{jsonPath}};

EOT;

    private const TMPL_ASSIGN_JSON_DATA_TO_FIELD_CASTING = <<<'EOT'
{{modelPath}} = {% if nullCheck ?? false -%}(null === {{jsonPath}}) ? null : {% endif %}({{type}}) {{jsonPath}};

EOT;

    private const TMPL_ASSIGN_DATETIME_TO_FIELD = <<<'EOT'
{{modelPath}} = new {{dateClass}}({{jsonPath}});

EOT;

    private const TMPL_ASSIGN_DATETIME_FROM_FORMAT = <<<'EOT'
{% if not formats %}
{{varDate}} = false;
{% endif %}
{% for format in formats %}
{{ loop.first ? '' : ' else'}} if (({{varDate}} = {{dateClass}}::createFromFormat({{format}}, {{jsonPath}}, {{timezone}}))) {
    {{modelPath}} = {{varDate}};
}
{% endfor %}
{{ formats ? ' else ' : "if (false === #{varDate}})" }} {
    throw new \Exception('Invalid datetime string '.({{jsonPath}}).' matches none of the deserialization formats: '.{{formatsError}});
}
unset({{varDate}});

EOT;

    private const TMPL_ASSIGN_SETTER = <<<'EOT'
{{modelPath}}->{{method}}({{value}});

EOT;

    private const TMPL_INIT_ARRAY = <<<'EOT'
{{modelPath}} = [];

EOT;

    private const TMPL_LOOP = <<<'EOT'
foreach ({{jsonPath}} as {{indexVariable}} => {{valueVariable}}) {
    {{code}}
}

EOT;

    private const TMPL_ARRAY_COLLECTION = <<<'EOT'
{{modelPath}} = new \Doctrine\Common\Collections\ArrayCollection({{tmpVariable}});

EOT;

    private const TMPL_UNSET = <<<'EOT'
unset({{variableNames|join(', ')}});

EOT;

    private const TMPL_ASSIGN_BACKED_ENUM = <<<'EOT'
{{modelPath}} = {% if nullCheck is not null -%}(null === {{jsonPath}}) ? null : {% endif %}{{enumClass}}::from({{jsonPath}});

EOT;

    private const TMPL_ASSIGN_UNIT_ENUM = <<<'EOT'
{{modelPath}} = match({{jsonPath}}) {
{% if nullCheck is not null -%}
    null => null,
{% endif %}
{% for case,name in enumCases %}
    {{name}} => {{enumClass}}::{{case}},
{% endfor %}
    default => throw new \ValueError("'{{'{' ~ jsonPath ~ '}'}}' is not a valid name for enum {{enumClass}}"),
};

EOT;

    private const TMPL_EXTRACT = '{{jsonPath}} ?? {{default}}';

    private const TMPL_CREATE_OBJECT = 'new {{className}}({{arguments|join(\', \')}})';

    private Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader(), ['autoescape' => false]);
    }

    public function renderFunction(string $name, string $className, string $jsonPath, string $code): string
    {
        return $this->render(self::TMPL_FUNCTION, [
            'functionName' => $name,
            'className' => $className,
            'jsonPath' => $jsonPath,
            'code' => $code,
        ]);
    }

    /**
     * @param list<string> $arguments
     */
    public function renderClass(string $modelPath, string $className, array $arguments, string $code, string $initArgumentsCode = ''): string
    {
        return $this->render(self::TMPL_CLASS, [
            'modelPath' => $modelPath,
            'className' => $className,
            'arguments' => $arguments,
            'code' => $code,
            'initArgumentsCode' => $initArgumentsCode,
        ]);
    }

    public function renderArgument(string $variableName, string $default, ?string $code): string
    {
        return $this->render(self::TMPL_ARGUMENT, [
            'variableName' => $variableName,
            'default' => $default,
            'code' => $code,
        ]);
    }

    public function renderPostMethod(string $modelPath, string $method): string
    {
        return $this->render(self::TMPL_POST_METHOD, [
            'modelPath' => $modelPath,
            'method' => $method,
        ]);
    }

    public function renderConditional(string $data, string $code): string
    {
        return $this->render(self::TMPL_CONDITIONAL, [
            'data' => $data,
            'code' => $code,
        ]);
    }

    public function renderDiscriminatorConditional(string $jsonPath, string $typeValue, string $code): string
    {
        return $this->render(self::TMPL_DISCRIMINATOR_CONDITIONAL, [
            'jsonPath' => $jsonPath,
            'typeValue' => $typeValue,
            'code' => $code,
        ]);
    }

    public function renderPrimitiveConditional(string $phpType, string $jsonPath, string $code, bool $withElseBlock = false): string
    {
        $typeCheck = self::PRIMITIVE_CHECKS[$phpType] ?? null;
        if (null === $typeCheck) {
            throw new \InvalidArgumentException(\sprintf('Provided type "%s" but only the following types are supported: %s', $phpType, implode(', ', array_keys(self::PRIMITIVE_CHECKS))));
        }

        $typeConditional = $this->render($typeCheck, [
            'value' => $jsonPath,
        ]);

        return $this->render(self::TMPL_PRIMITIVE_CONDITIONAL, [
            'typeConditional' => $typeConditional,
            'code' => $code,
            'withElseBlock' => $withElseBlock,
        ]);
    }

    public function renderKeyExistsConditional(string $data, string $key, string $code, ?string $elseCode = null): string
    {
        return $this->render(self::TMPL_KEY_EXISTS_CONDITIONAL, [
            'data' => $data,
            'index' => var_export($key, true),
            'code' => $code,
            'elseCode' => $elseCode,
        ]);
    }

    public function renderDynamicKeyExistsConditional(string $data, string $key, string $code, ?string $elseCode = null): string
    {
        return $this->render(self::TMPL_KEY_EXISTS_CONDITIONAL, [
            'data' => $data,
            'index' => $key,
            'code' => $code,
            'elseCode' => $elseCode,
        ]);
    }

    public function renderIsNullConditional(string $jsonPath, string $code, ?string $elseCode): string
    {
        return $this->render(self::TMPL_IS_NULL_CONDITIONAL, [
            'jsonPath' => $jsonPath,
            'code' => $code,
            'elseCode' => $elseCode,
        ]);
    }

    public function renderAssignJsonDataToField(string $modelPath, string $jsonPath): string
    {
        return $this->render(self::TMPL_ASSIGN_JSON_DATA_TO_FIELD, [
            'modelPath' => $modelPath,
            'jsonPath' => $jsonPath,
        ]);
    }

    public function renderAssignJsonDataToFieldWithCast(string $phpType, string $modelPath, string $jsonPath): string
    {
        $typeCast = self::PRIMITIVE_CASTS[$phpType] ?? null;
        if (null !== $typeCast) {
            $jsonPath = $this->render($typeCast, [
                'value' => $jsonPath,
            ]);
        }

        return $this->render(self::TMPL_ASSIGN_JSON_DATA_TO_FIELD, [
            'modelPath' => $modelPath,
            'jsonPath' => $jsonPath,
        ]);
    }

    public function renderAssignJsonDataToFieldWithCasting(string $modelPath, string $jsonPath, string $type, bool $nullCheck = false): string
    {
        return $this->render(self::TMPL_ASSIGN_JSON_DATA_TO_FIELD_CASTING, [
            'modelPath' => $modelPath,
            'jsonPath' => $jsonPath,
            'type' => $type,
            'nullCheck' => $nullCheck,
        ]);
    }

    public function renderAssignDateTimeToField(bool $immutable, string $modelPath, string $jsonPath, bool $nullCheck = false): string
    {
        $dateClass = $immutable ? \DateTimeImmutable::class : \DateTime::class;

        return $this->render(self::TMPL_ASSIGN_DATETIME_TO_FIELD, [
            'modelPath' => $modelPath,
            'jsonPath' => $jsonPath,
            'dateClass' => $dateClass,
            'nullCheck' => $nullCheck,
        ]);
    }

    /**
     * @param list<string> $formats
     */
    public function renderAssignDateTimeFromFormat(bool $immutable, string $modelPath, string $jsonPath, array $formats, ?string $timezone = null, bool $nullCheck = false): string
    {
        $dateClass = $immutable ? \DateTimeImmutable::class : \DateTime::class;
        $formats = array_map(
            static fn (string $f): string => var_export($f, true),
            $formats
        );
        $formatsError = var_export(implode(',', $formats), true);
        $dateVariable = preg_replace_callback(
            '/([^a-zA-Z]+|\d+)([a-zA-Z])/',
            static fn ($match): string => (ctype_digit($match[1]) ? $match[1] : null).mb_strtoupper($match[2]),
            $modelPath
        ).'Date';

        return $this->render(self::TMPL_ASSIGN_DATETIME_FROM_FORMAT, [
            'modelPath' => $modelPath,
            'jsonPath' => $jsonPath,
            'formats' => $formats,
            'formatsError' => $formatsError,
            'dateClass' => $dateClass,
            'varFormat' => '$'.lcfirst($dateVariable).'Format',
            'varDate' => '$'.lcfirst($dateVariable),
            'timezone' => $timezone ? 'new \DateTimeZone('.var_export($timezone, true).')' : 'null',
            'nullCheck' => $nullCheck,
        ]);
    }

    public function renderAssignBackedEnum(string $enumClass, string $modelPath, string $jsonPath, bool $nullCheck): string
    {
        return $this->render(self::TMPL_ASSIGN_BACKED_ENUM, [
            'enumClass' => $enumClass,
            'modelPath' => $modelPath,
            'jsonPath' => $jsonPath,
            'nullCheck' => $nullCheck,
        ]);
    }

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    public function renderAssignUnitEnum(string $enumClass, string $modelPath, string $jsonPath, bool $nullCheck): string
    {
        $cases = array_column($enumClass::cases(), 'name', 'name');
        $cases = array_map(static fn (string $f): string => var_export($f, true), $cases);

        return $this->render(self::TMPL_ASSIGN_UNIT_ENUM, [
            'enumClass' => $enumClass,
            'enumCases' => $cases,
            'modelPath' => $modelPath,
            'jsonPath' => $jsonPath,
            'nullCheck' => $nullCheck,
        ]);
    }

    public function renderExtract(?string $jsonPath, string $default = 'null'): string
    {
        return $this->render(self::TMPL_EXTRACT, [
            'jsonPath' => $jsonPath,
            'default' => $default,
        ]);
    }

    /**
     * @param list<string> $arguments
     */
    public function renderCreateObject(string $className, array $arguments): string
    {
        return $this->render(self::TMPL_CREATE_OBJECT, [
            'className' => $className,
            'arguments' => $arguments,
        ]);
    }

    public function renderSetter(string $modelPath, string $method, string $value): string
    {
        return $this->render(self::TMPL_ASSIGN_SETTER, [
            'modelPath' => $modelPath,
            'method' => $method,
            'value' => $value,
        ]);
    }

    public function renderInitArray(string $modelPath): string
    {
        return $this->render(self::TMPL_INIT_ARRAY, [
            'modelPath' => $modelPath,
        ]);
    }

    public function renderLoop(string $jsonPath, string $indexVariable, string $valueVariable, string $code): string
    {
        return $this->render(self::TMPL_LOOP, [
            'jsonPath' => $jsonPath,
            'indexVariable' => $indexVariable,
            'valueVariable' => $valueVariable,
            'code' => $code,
        ]);
    }

    public function renderArrayCollection(string $modelPath, string $tmpVariable): string
    {
        return $this->render(self::TMPL_ARRAY_COLLECTION, [
            'modelPath' => $modelPath,
            'tmpVariable' => $tmpVariable,
        ]);
    }

    /**
     * @param string[] $variableNames
     */
    public function renderUnset(array $variableNames): string
    {
        return $this->render(self::TMPL_UNSET, [
            'variableNames' => $variableNames,
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
}
