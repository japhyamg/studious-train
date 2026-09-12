<?php

namespace App\Services;

class RuleEvaluation
{
    private bool $triggered = false;
    private ?int $transactionId = null;
    private ?string $flaggedAccount = null;
    private array $relatedTransactionIds = [];
    private bool $isAccountLevel = false;

    public function shouldTrigger(): bool
    {
        return $this->triggered;
    }

    public function setTriggered(bool $triggered): self
    {
        $this->triggered = $triggered;
        return $this;
    }

    public function getTransactionId(): ?int
    {
        return $this->transactionId;
    }

    public function setTransactionId(?int $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getFlaggedAccount(): ?string
    {
        return $this->flaggedAccount;
    }

    public function setFlaggedAccount(?string $account): self
    {
        $this->flaggedAccount = $account;
        return $this;
    }

    public function hasRelatedTransactions(): bool
    {
        return count($this->relatedTransactionIds) > 0;
    }

    public function getRelatedTransactionIds(): array
    {
        return $this->relatedTransactionIds;
    }

    public function setRelatedTransactionIds(array $ids): self
    {
        $this->relatedTransactionIds = $ids;
        return $this;
    }

    public function isAccountLevel(): bool
    {
        return $this->isAccountLevel;
    }

    public function setIsAccountLevel(bool $isAccountLevel): self
    {
        $this->isAccountLevel = $isAccountLevel;
        return $this;
    }

    public function getNotificationMessage($rule, $case): string
    {
        return "Rule '{$rule->name}' triggered for case #{$case->slug}.";
    }
}
