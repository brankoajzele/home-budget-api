<?php

// src/Security/ApiKeyAuthenticator.php
namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use App\Repository\UserRepository;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public const HOME_BUDGET_HEADER_AUTH_TOKEN = 'HB-AUTH-TOKEN';

    public function __construct(
        private readonly UserRepository $userRepository
    )
    {
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::HOME_BUDGET_HEADER_AUTH_TOKEN);
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->headers->get(self::HOME_BUDGET_HEADER_AUTH_TOKEN);

        if (null === $token) {
            throw new CustomUserMessageAuthenticationException('Authentication failed; no ' . self::HOME_BUDGET_HEADER_AUTH_TOKEN . ' token provided in header');
        }

        return new SelfValidatingPassport(
            new UserBadge($token, function () use ($token) {
                return $this->userRepository->findOneBy(['token' => $token]);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}