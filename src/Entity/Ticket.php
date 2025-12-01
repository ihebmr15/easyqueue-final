<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Service $service = null;

    #[ORM\Column(length: 255)]
    private ?string $servicename = null;

    #[ORM\Column]
    private ?int $ticketnumber = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTime $createdat = null;

    #[ORM\Column]
    private ?int $estimatedwait = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getServicename(): ?string
    {
        return $this->servicename;
    }

    public function setServicename(string $servicename): static
    {
        $this->servicename = $servicename;

        return $this;
    }

    public function getTicketnumber(): ?int
    {
        return $this->ticketnumber;
    }

    public function setTicketnumber(int $ticketnumber): static
    {
        $this->ticketnumber = $ticketnumber;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedat(): ?\DateTime
    {
        return $this->createdat;
    }

    public function setCreatedat(\DateTime $createdat): static
    {
        $this->createdat = $createdat;

        return $this;
    }

    public function getEstimatedwait(): ?int
    {
        return $this->estimatedwait;
    }

    public function setEstimatedwait(int $estimatedwait): static
    {
        $this->estimatedwait = $estimatedwait;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        if ($service) {
            $this->servicename = $service->getName();
        }

        return $this;
    }
}
