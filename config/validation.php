<?php
// File: config/validation.php
// Shared validation utilities for all forms

class Validation {
    private static array $errors = [];

    public static function errors(): array {
        return self::$errors;
    }

    public static function hasErrors(): bool {
        return !empty(self::$errors);
    }

    public static function firstError(): string {
        return self::$errors[0] ?? '';
    }

    public static function clear(): void {
        self::$errors = [];
    }

    public static function required(array $data, array $fields): bool {
        self::clear();
        foreach ($fields as $field) {
            $value = $data[$field] ?? '';
            if (is_string($value)) $value = trim($value);
            if ($value === '' || $value === null) {
                $label = ucfirst(str_replace('_', ' ', $field));
                self::$errors[] = "{$label} is required.";
            }
        }
        return !self::hasErrors();
    }

    public static function username(string $username): bool {
        $username = trim($username);
        if (strlen($username) < 3) {
            self::$errors[] = 'Username must be at least 3 characters.';
            return false;
        }
        if (strlen($username) > 50) {
            self::$errors[] = 'Username must not exceed 50 characters.';
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            self::$errors[] = 'Username may only contain letters, numbers, and underscores.';
            return false;
        }
        if (preg_match('/(.)\1{3,}/', $username)) {
            self::$errors[] = 'Username contains too many repeated characters.';
            return false;
        }
        if (preg_match('/^(admin|root|system|test|user)$/i', $username)) {
            self::$errors[] = 'That username is reserved. Please choose another.';
            return false;
        }
        return true;
    }

    public static function uniqueUsername(PDO $pdo, string $username, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as cnt FROM users WHERE username = ?";
        $params = [$username];
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()['cnt'] > 0) {
            self::$errors[] = 'This username is already taken.';
            return false;
        }
        return true;
    }

    public static function fullName(string $name): bool {
        $name = trim($name);
        if (strlen($name) < 2) {
            self::$errors[] = 'Full name must be at least 2 characters.';
            return false;
        }
        if (strlen($name) > 100) {
            self::$errors[] = 'Full name must not exceed 100 characters.';
            return false;
        }
        if (!preg_match("/^[\\p{L}\\s\\-\\.']+$/u", $name)) {
            self::$errors[] = 'Full name may only contain letters, spaces, hyphens, and periods.';
            return false;
        }
        if (preg_match('/(.)\1{3,}/', $name)) {
            self::$errors[] = 'Full name contains too many repeated characters.';
            return false;
        }
        return true;
    }

    public static function email(string $email): bool {
        $email = trim($email);
        if ($email === '') return true;
        if (strlen($email) > 100) {
            self::$errors[] = 'Email must not exceed 100 characters.';
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$errors[] = 'Please enter a valid email address.';
            return false;
        }
        return true;
    }

    public static function uniqueEmail(PDO $pdo, string $email, ?int $excludeId = null): bool {
        $email = trim($email);
        if ($email === '') return true;
        $sql = "SELECT COUNT(*) as cnt FROM users WHERE email = ?";
        $params = [$email];
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()['cnt'] > 0) {
            self::$errors[] = 'This email address is already registered.';
            return false;
        }
        return true;
    }

    public static function phone(string $phone): bool {
        $phone = trim($phone);
        if ($phone === '') return true;
        if (strlen($phone) > 20) {
            self::$errors[] = 'Phone number must not exceed 20 characters.';
            return false;
        }
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        // Zambian format: 260XXXXXXXXX or 0XXXXXXXXX
        if (preg_match('/^(?:\+?260|0)\d{9}$/', $cleaned)) return true;
        // Generic international fallback
        if (preg_match('/^\+?\d{7,15}$/', $cleaned)) return true;
        self::$errors[] = 'Please enter a valid phone number (e.g., 0977123456 or +260977123456).';
        return false;
    }

    public static function password(string $password, int $minLength = 8): bool {
        if (strlen($password) < $minLength) {
            self::$errors[] = "Password must be at least {$minLength} characters.";
            return false;
        }
        if (strlen($password) > 128) {
            self::$errors[] = 'Password must not exceed 128 characters.';
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            self::$errors[] = 'Password must contain at least one uppercase letter.';
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            self::$errors[] = 'Password must contain at least one digit.';
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            self::$errors[] = 'Password must contain at least one lowercase letter.';
            return false;
        }
        return true;
    }

    public static function match(string $value1, string $value2, string $label = 'Confirmation'): bool {
        if ($value1 !== $value2) {
            self::$errors[] = "{$label} does not match.";
            return false;
        }
        return true;
    }

    public static function groupExists(PDO $pdo, int $groupId): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM savings_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        if ($stmt->fetch()['cnt'] === 0) {
            self::$errors[] = 'The selected savings group does not exist.';
            return false;
        }
        return true;
    }

    public static function positiveNumber($value, string $label = 'Amount'): bool {
        if (!is_numeric($value) || floatval($value) < 0) {
            self::$errors[] = "{$label} must be a positive number.";
            return false;
        }
        return true;
    }

    public static function sanitize(string $input): string {
        return trim(strip_tags($input));
    }

    public static function sanitizeArray(array $data): array {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $clean[$key] = self::sanitize($value);
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitizeArray($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}