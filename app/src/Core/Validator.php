<?php
declare(strict_types=1);

namespace App\Core;

/** Small server-side validator. Error messages are translated at creation time. */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $in)
    {
    }

    public function fail(string $field, string $key, array $params = []): void
    {
        $this->errors[$field] ??= t($key, $params);
    }

    public function str(string $f): string
    {
        $v = $this->in[$f] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    public function required(string $f, string $labelKey): self
    {
        if ($this->str($f) === '') {
            $this->fail($f, 'validation.required', ['field' => t($labelKey)]);
        }
        return $this;
    }

    public function max(string $f, string $labelKey, int $n): self
    {
        if (mb_strlen($this->str($f)) > $n) {
            $this->fail($f, 'validation.max_length', ['field' => t($labelKey), 'max' => $n]);
        }
        return $this;
    }

    public function email(string $f, string $labelKey): self
    {
        $v = $this->str($f);
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->fail($f, 'validation.email', ['field' => t($labelKey)]);
        }
        return $this;
    }

    public function in(string $f, string $labelKey, array $allowed, bool $required = true): self
    {
        $v = $this->str($f);
        if ($v === '' && !$required) {
            return $this;
        }
        if (!in_array($v, array_map('strval', $allowed), true)) {
            $this->fail($f, 'validation.invalid_choice', ['field' => t($labelKey)]);
        }
        return $this;
    }

    public function intRange(string $f, string $labelKey, int $min, int $max): self
    {
        $v = $this->str($f);
        if (!preg_match('/^\d{1,9}$/', $v) || (int)$v < $min || (int)$v > $max) {
            $this->fail($f, 'validation.int_range', ['field' => t($labelKey), 'min' => $min, 'max' => $max]);
        }
        return $this;
    }

    public function date(string $f, string $labelKey, bool $required = true): self
    {
        $v = $this->str($f);
        if ($v === '') {
            if ($required) {
                $this->fail($f, 'validation.required', ['field' => t($labelKey)]);
            }
            return $this;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v || (int)$d->format('Y') < 1990 || (int)$d->format('Y') > 2100) {
            $this->fail($f, 'validation.date', ['field' => t($labelKey)]);
        }
        return $this;
    }

    public function ok(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
