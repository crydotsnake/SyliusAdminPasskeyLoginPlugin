<?php

declare(strict_types=1);

namespace SKrull\SyliusAdminPasskeyLoginPlugin\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SKrull\SyliusAdminPasskeyLoginPlugin\Repository\AdminPasskeyRepository;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\TrustPath;

#[ORM\Entity(repositoryClass: AdminPasskeyRepository::class)]
#[ORM\Table(name: 'skrull_admin_passkey')]
#[ORM\Index(name: 'skrull_admin_passkey_user_handle_idx', columns: ['user_handle'])]
class AdminPasskey
{
    public const NAME_MAX_LENGTH = 100;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdminUserInterface::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', nullable: false, onDelete: 'CASCADE')]
    private AdminUserInterface $adminUser;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'credential_id_hash', type: Types::STRING, length: 64, unique: true)]
    private string $credentialIdHash;

    #[ORM\Column(name: 'public_key_credential_id', type: 'base64')]
    private string $publicKeyCredentialId;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $type;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $transports;

    #[ORM\Column(name: 'attestation_type', type: Types::STRING, length: 32)]
    private string $attestationType;

    #[ORM\Column(name: 'trust_path', type: 'trust_path')]
    private TrustPath $trustPath;

    #[ORM\Column(type: 'aaguid')]
    private Uuid $aaguid;

    #[ORM\Column(name: 'credential_public_key', type: 'base64')]
    private string $credentialPublicKey;

    #[ORM\Column(name: 'user_handle', type: Types::STRING, length: 64)]
    private string $userHandle;

    #[ORM\Column(type: Types::BIGINT)]
    private int|string $counter;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'other_ui', type: Types::JSON, nullable: true)]
    private ?array $otherUI;

    #[ORM\Column(name: 'backup_eligible', type: Types::BOOLEAN, nullable: true)]
    private ?bool $backupEligible;

    #[ORM\Column(name: 'backup_status', type: Types::BOOLEAN, nullable: true)]
    private ?bool $backupStatus;

    #[ORM\Column(name: 'uv_initialized', type: Types::BOOLEAN, nullable: true)]
    private ?bool $uvInitialized;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_used_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    private function __construct(AdminUserInterface $adminUser, CredentialRecord $credentialRecord, \DateTimeImmutable $createdAt)
    {
        $this->adminUser = $adminUser;
        $this->createdAt = $createdAt;
        $this->publicKeyCredentialId = $credentialRecord->publicKeyCredentialId;
        $this->credentialIdHash = self::hashCredentialId($credentialRecord->publicKeyCredentialId);
        $this->type = $credentialRecord->type;
        $this->transports = array_values($credentialRecord->transports);
        $this->attestationType = $credentialRecord->attestationType;
        $this->trustPath = $credentialRecord->trustPath;
        $this->aaguid = $credentialRecord->aaguid;
        $this->credentialPublicKey = $credentialRecord->credentialPublicKey;
        $this->userHandle = $credentialRecord->userHandle;
        $this->updateFromCredentialRecord($credentialRecord);
    }

    public static function fromCredentialRecord(
        AdminUserInterface $adminUser,
        CredentialRecord $credentialRecord,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($adminUser, $credentialRecord, $createdAt);
    }

    public static function hashCredentialId(string $publicKeyCredentialId): string
    {
        return hash('sha256', $publicKeyCredentialId);
    }

    public function toCredentialRecord(): CredentialRecord
    {
        return CredentialRecord::create(
            $this->publicKeyCredentialId,
            $this->type,
            $this->transports,
            $this->attestationType,
            $this->trustPath,
            $this->aaguid,
            $this->credentialPublicKey,
            $this->userHandle,
            (int) $this->counter,
            $this->otherUI,
            $this->backupEligible,
            $this->backupStatus,
            $this->uvInitialized,
        );
    }

    public function updateFromCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $this->counter = $credentialRecord->counter;
        /** @var array<string, mixed>|null $otherUI */
        $otherUI = $credentialRecord->otherUI;
        $this->otherUI = $otherUI;
        $this->backupEligible = $credentialRecord->backupEligible;
        $this->backupStatus = $credentialRecord->backupStatus;
        $this->uvInitialized = $credentialRecord->uvInitialized;
    }

    public function markAsUsed(\DateTimeImmutable $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdminUser(): AdminUserInterface
    {
        return $this->adminUser;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUserHandle(): string
    {
        return $this->userHandle;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public static function normalizeName(mixed $name): ?string
    {
        if (!is_string($name)) {
            return null;
        }

        $name = trim($name);

        return '' !== $name ? mb_substr($name, 0, self::NAME_MAX_LENGTH) : null;
    }
}
