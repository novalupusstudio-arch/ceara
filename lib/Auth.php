<?php

final class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function login(string $username, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? AND active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
    }
}

