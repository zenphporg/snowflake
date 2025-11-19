const commitAnalyzerOptions = {
	preset: 'angular',
	releaseRules: [
		{ type: 'breaking', release: 'major' },
		{ type: 'feat', release: 'minor' },
		{ type: 'fix', release: 'patch' },
		{ type: 'task', release: 'patch' },
		{ type: 'refactor', release: 'patch' },
		{ type: 'docs', release: 'patch' },
		{ type: 'chore', release: false },
		{ scope: 'style', release: false },
		{ scope: 'test', release: false },
		{ scope: 'deploy', release: false },
	],
	parserOpts: {
		noteKeywords: [],
	},
}

const releaseNotesGeneratorOptions = {
	writerOpts: {
		transform: (commit, context) => {
			// Create a mutable copy of the commit object
			const modifiedCommit = { ...commit }
			const issues = []

			const types = {
				breaking: 'Breaking',
				feat: 'Features',
				fix: 'Bug Fixes',
				task: 'Task Commit',
				refactor: 'Code Refactoring',
				docs: 'Documentation',
				chore: 'Maintenance',
			}

			modifiedCommit.type = types[commit.type]

			if (typeof commit.hash === 'string') {
				modifiedCommit.shortHash = commit.hash.substring(0, 7)
			}

			if (typeof commit.subject === 'string') {
				let url = context.repository ? `${context.host}/${context.owner}/${context.repository}` : context.repoUrl
				if (url) {
					url = `${url}/issues/`
					// Issue URLs.
					modifiedCommit.subject = commit.subject.replace(/#([0-9]+)/g, (_, issue) => {
						issues.push(issue)
						return `[#${issue}](${url}${issue})`
					})
				}
				if (context.host) {
					// User URLs.
					modifiedCommit.subject = modifiedCommit.subject.replace(
						/\B@([a-z0-9](?:-?[a-z0-9/]){0,38})/g,
						(_, username) => {
							if (username.includes('/')) {
								return `@${username}`
							}

							return `[@${username}](${context.host}/${username})`
						}
					)
				}
			}

			// remove references that already appear in the subject
			modifiedCommit.references = commit.references.filter((reference) => {
				if (issues.indexOf(reference.issue) === -1) {
					return true
				}

				return false
			})

			return modifiedCommit
		},
	},
}

module.exports = {
	debug: true,
	branches: ['main'],
	repositoryUrl: 'https://gitlab.com/zenphp/snowflake',

	plugins: [
		// analyze commits with conventional-changelog
		['@semantic-release/commit-analyzer', commitAnalyzerOptions],
		// generate changelog content with conventional-changelog
		['@semantic-release/release-notes-generator', releaseNotesGeneratorOptions],
		// updates the changelog file
		[
			'@semantic-release/changelog',
			{
				changelogFile: 'CHANGELOG.md',
				changelogTitle: '# Snowflake Changelog',
			},
		],
		// creating a new version commit
		[
			'@semantic-release/git',
			{
				assets: ['CHANGELOG.md'],
			},
		],
		'@semantic-release/gitlab',
	],
}
