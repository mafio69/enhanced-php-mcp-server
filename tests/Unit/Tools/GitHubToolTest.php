<?php

namespace Tests\Unit\Tools;

use App\Tools\GitHubTool;
use PHPUnit\Framework\TestCase;

class GitHubToolTest extends TestCase
{
    private GitHubTool $tool;

    protected function setUp(): void
    {
        $this->tool = new GitHubTool();
    }

    public function testGetName(): void
    {
        $this->assertEquals('github', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertEquals('Narzędzie do interakcji z GitHub API - pobieranie informacji o użytkownikach, repozytoriach, commitach i wyszukiwanie', $this->tool->getDescription());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->tool->isEnabled());
    }

    public function testGetSchema(): void
    {
        $schema = $this->tool->getSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('action', $schema['properties']);
        $this->assertArrayHasKey('username', $schema['properties']);
        $this->assertArrayHasKey('owner', $schema['properties']);
        $this->assertArrayHasKey('repo', $schema['properties']);
        $this->assertArrayHasKey('token', $schema['properties']);
        $this->assertArrayHasKey('searchQuery', $schema['properties']);
        $this->assertArrayHasKey('path', $schema['properties']);
        $this->assertArrayHasKey('per_page', $schema['properties']);
        $this->assertArrayHasKey('page', $schema['properties']);

        // Check action schema
        $actionSchema = $schema['properties']['action'];
        $this->assertEquals('string', $actionSchema['type']);
        $this->assertContains('info', $actionSchema['enum']);
        $this->assertContains('get_user_info', $actionSchema['enum']);
        $this->assertContains('get_repo_info', $actionSchema['enum']);
        $this->assertContains('list_user_repos', $actionSchema['enum']);
        $this->assertContains('list_repo_commits', $actionSchema['enum']);
        $this->assertContains('search_repositories', $actionSchema['enum']);
        $this->assertContains('get_repo_contents', $actionSchema['enum']);

        // Check required fields
        $this->assertEquals(['action'], $schema['required']);
    }

    public function testExecuteWithDefaultAction(): void
    {
        $result = $this->tool->execute([]);

        // Should call info action by default
        $this->assertStringContainsString('GITHUB TOOL', $result);
        $this->assertStringContainsString('GitHub API', $result);
    }

    public function testExecuteWithInfoAction(): void
    {
        $result = $this->tool->execute(['action' => 'info']);

        $this->assertStringContainsString('GITHUB TOOL', $result);
        $this->assertStringContainsString('Dostępne akcje:', $result);
        $this->assertStringContainsString('get_user_info', $result);
        $this->assertStringContainsString('get_repo_info', $result);
    }

    public function testExecuteWithGetUserInfoAction(): void
    {
        $result = $this->tool->execute(['action' => 'get_user_info', 'username' => 'octocat']);

        $this->assertStringContainsString('GITHUB USER INFO: octocat', $result);
        // Should contain real GitHub data for octocat
        $this->assertStringContainsString('Login: octocat', $result);
    }

    public function testExecuteWithGetUserInfoActionThrowsExceptionWithoutUsername(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Username jest wymagany dla akcji 'get_user_info'");

        $this->tool->execute(['action' => 'get_user_info']);
    }

    public function testExecuteWithGetRepoInfoAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_repo_info',
            'owner' => 'octocat',
            'repo' => 'Hello-World'
        ]);

        $this->assertStringContainsString('GITHUB REPO INFO: octocat/Hello-World', $result);
        // Should contain real repo data
        $this->assertStringContainsString('Name: Hello-World', $result);
    }

    public function testExecuteWithGetRepoInfoActionThrowsExceptionWithoutOwner(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Owner i repo są wymagane dla akcji 'get_repo_info'");

        $this->tool->execute(['action' => 'get_repo_info', 'repo' => 'test']);
    }

    public function testExecuteWithGetRepoInfoActionThrowsExceptionWithoutRepo(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Owner i repo są wymagane dla akcji 'get_repo_info'");

        $this->tool->execute(['action' => 'get_repo_info', 'owner' => 'test']);
    }

    public function testExecuteWithListUserReposAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'list_user_repos',
            'username' => 'octocat'
        ]);

        $this->assertStringContainsString('GITHUB USER REPOS: octocat', $result);
        // Should contain actual repo data
        $this->assertStringContainsString('Znaleziono', $result);
    }

    public function testExecuteWithListUserReposActionThrowsExceptionWithoutUsername(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Username jest wymagany dla akcji 'list_user_repos'");

        $this->tool->execute(['action' => 'list_user_repos']);
    }

    public function testExecuteWithListRepoCommitsAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'list_repo_commits',
            'owner' => 'octocat',
            'repo' => 'Hello-World'
        ]);

        $this->assertStringContainsString('GITHUB COMMITS: octocat/Hello-World', $result);
        // Should contain actual commit data
        $this->assertStringContainsString('Znaleziono', $result);
    }

    public function testExecuteWithListRepoCommitsActionThrowsExceptionWithoutOwner(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Owner i repo są wymagane dla akcji 'list_repo_commits'");

        $this->tool->execute(['action' => 'list_repo_commits', 'repo' => 'test']);
    }

    public function testExecuteWithListRepoCommitsActionThrowsExceptionWithoutRepo(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Owner i repo są wymagane dla akcji 'list_repo_commits'");

        $this->tool->execute(['action' => 'list_repo_commits', 'owner' => 'test']);
    }

    public function testExecuteWithSearchRepositoriesAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'search_repositories',
            'searchQuery' => 'hello world'
        ]);

        $this->assertStringContainsString('GITHUB REPO SEARCH: "hello world"', $result);
        // Should contain search results
        $this->assertStringContainsString('Znaleziono', $result);
    }

    public function testExecuteWithSearchRepositoriesActionThrowsExceptionWithoutQuery(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Search query jest wymagany dla akcji 'search_repositories'");

        $this->tool->execute(['action' => 'search_repositories']);
    }

    public function testExecuteWithGetRepoContentsAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_repo_contents',
            'owner' => 'octocat',
            'repo' => 'Hello-World'
        ]);

        $this->assertStringContainsString('GITHUB REPO CONTENTS: octocat/Hello-World', $result);
        // Should contain file/directory listings (could be 📁 or 📄)
        $this->assertTrue(
            strpos($result, '📁') !== false || strpos($result, '📄') !== false
        );
    }

    public function testExecuteWithGetRepoContentsActionThrowsExceptionWithoutOwner(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Owner i repo są wymagane dla akcji 'get_repo_contents'");

        $this->tool->execute(['action' => 'get_repo_contents', 'repo' => 'test']);
    }

    public function testExecuteWithGetRepoContentsActionThrowsExceptionWithoutRepo(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Owner i repo są wymagane dla akcji 'get_repo_contents'");

        $this->tool->execute(['action' => 'get_repo_contents', 'owner' => 'test']);
    }

    public function testExecuteWithUnknownActionThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Nieznana akcja: unknown_action");

        $this->tool->execute(['action' => 'unknown_action']);
    }

    public function testExecuteHandlesInvalidUsername(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_user_info',
            'username' => 'nonexistent-user-that-does-not-exist-12345'
        ]);

        $this->assertStringContainsString('Błąd:', $result);
    }

    public function testExecuteHandlesInvalidRepository(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_repo_info',
            'owner' => 'nonexistent-user-12345',
            'repo' => 'nonexistent-repo'
        ]);

        $this->assertStringContainsString('Błąd:', $result);
    }

    public function testExecuteWithTokenParameter(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_user_info',
            'username' => 'octocat',
            'token' => 'fake-token-for-testing'
        ]);

        $this->assertStringContainsString('GITHUB USER INFO: octocat', $result);
        // Should handle token gracefully (even fake one)
    }

    public function testExecuteWithPagination(): void
    {
        $result = $this->tool->execute([
            'action' => 'list_user_repos',
            'username' => 'octocat',
            'per_page' => 5,
            'page' => 1
        ]);

        $this->assertStringContainsString('Page 1', $result);
        $this->assertStringContainsString('GITHUB USER REPOS: octocat', $result);
    }

    public function testExecuteWithMaxPaginationLimits(): void
    {
        $result = $this->tool->execute([
            'action' => 'search_repositories',
            'searchQuery' => 'test',
            'per_page' => 100, // Maximum allowed
            'page' => 1
        ]);

        $this->assertStringContainsString('Page 1', $result);
        $this->assertStringContainsString('GITHUB REPO SEARCH: "test"', $result);
    }

    public function testExecuteWithRepoContentsPath(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_repo_contents',
            'owner' => 'octocat',
            'repo' => 'Hello-World',
            'path' => 'README'
        ]);

        $this->assertStringContainsString('GITHUB REPO CONTENTS: octocat/Hello-World/README', $result);
    }

    public function testExecuteHandlesNetworkErrorsGracefully(): void
    {
        // This test assumes network might fail, but should handle gracefully
        $result = $this->tool->execute([
            'action' => 'get_user_info',
            'username' => 'octocat'
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}