module.exports = {
  branch: 'master',
  analyzeCommits: 'semantic-release-conventional-commits',
  repositoryUrl: 'https://github.com/e0ipso/travis-drupal-module.git',
  plugins: [
    '@semantic-release/commit-analyzer',
    '@semantic-release/release-notes-generator',
    '@semantic-release/github',
  ],
};
