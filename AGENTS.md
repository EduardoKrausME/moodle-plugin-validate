# AGENTS.md

This repository contains mandatory post-processing tools for Moodle plugins.

Before declaring any Moodle plugin creation, modification, refactor, or review complete, read `.chatgpt/INSTRUCTIONS.md` and follow it.

When a Moodle plugin is available on disk, run:

```bash
python3 .chatgpt/run.py /absolute/path/to/plugin
```

Do not skip a failing post-processing step. Fix the plugin and rerun the command until it exits successfully.
