<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120100242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create skrull_admin_passkey table (MySQL/MariaDB).';
    }

    public function preUp(Schema $schema): void
    {
        $this->skipIf(!$this->isMySql(), 'This migration can only be executed on MySQL/MariaDB.');
    }

    public function preDown(Schema $schema): void
    {
        $this->skipIf(!$this->isMySql(), 'This migration can only be executed on MySQL/MariaDB.');
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE skrull_admin_passkey (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) DEFAULT NULL, credential_id_hash VARCHAR(64) NOT NULL, public_key_credential_id LONGTEXT NOT NULL, type VARCHAR(32) NOT NULL, transports JSON NOT NULL, attestation_type VARCHAR(32) NOT NULL, trust_path JSON NOT NULL, aaguid LONGTEXT NOT NULL, credential_public_key LONGTEXT NOT NULL, user_handle VARCHAR(64) NOT NULL, counter BIGINT NOT NULL, other_ui JSON DEFAULT NULL, backup_eligible TINYINT DEFAULT NULL, backup_status TINYINT DEFAULT NULL, uv_initialized TINYINT DEFAULT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, admin_user_id INT NOT NULL, UNIQUE INDEX UNIQ_D1996C0F6EBA8C16 (credential_id_hash), INDEX IDX_D1996C0F6352511C (admin_user_id), INDEX skrull_admin_passkey_user_handle_idx (user_handle), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE skrull_admin_passkey ADD CONSTRAINT FK_D1996C0F6352511C FOREIGN KEY (admin_user_id) REFERENCES sylius_admin_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE skrull_admin_passkey DROP FOREIGN KEY FK_D1996C0F6352511C');
        $this->addSql('DROP TABLE skrull_admin_passkey');
    }

    private function isMySql(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }
}
