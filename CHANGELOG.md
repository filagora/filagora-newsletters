# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- The six newsletter tables (subscribers, newsletters, sends, events, content blocks, link clicks) with a schema snapshot equal to the live tables of the site the package was extracted from, so `filagora:adopt` accepts them.
- Models, swappable through `filagora-newsletters.models`, and the five status and type enums.
