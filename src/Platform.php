<?php

declare(strict_types=1);

/**
 * The few variables Clever Cloud injects that this demo shows.
 * Nothing else from the environment is ever printed.
 */
final class Platform
{
    public const SHOWN = [
        'CC_APP_NAME' => 'Application',
        'APP_ID' => 'App ID',
        'INSTANCE_NUMBER' => 'Instance number',
        'CC_PRETTY_INSTANCE_NAME' => 'Instance name',
        'INSTANCE_TYPE' => 'Instance type',
        'CC_COMMIT_ID' => 'Commit',
        'CC_DEPLOYMENT_ID' => 'Deployment',
    ];

    public static function get(string $name): string
    {
        if (!isset(self::SHOWN[$name])) {
            throw new InvalidArgumentException("$name is not a shown variable");
        }
        $value = (string) getenv($name);

        return $name === 'CC_COMMIT_ID' ? substr($value, 0, 7) : $value;
    }

    /** @return array<string, array{label: string, value: string}> */
    public static function panel(): array
    {
        $rows = [];
        foreach (self::SHOWN as $name => $label) {
            $rows[$name] = ['label' => $label, 'value' => self::get($name)];
        }

        return $rows;
    }

    public static function onClever(): bool
    {
        return self::get('APP_ID') !== '';
    }

    /** Who is holding the pen: the instance answering this request. */
    public static function who(): array
    {
        $number = self::get('INSTANCE_NUMBER');

        return [
            'instance' => $number === '' ? 'local' : $number,
            'name' => self::get('CC_PRETTY_INSTANCE_NAME') ?: 'Local machine',
            'deployment' => self::get('CC_DEPLOYMENT_ID') ?: 'local',
            'commit' => self::get('CC_COMMIT_ID') ?: 'local',
        ];
    }
}
