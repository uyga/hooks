<?php

class HookReceive
{
    const ZERO = '0000000000000000000000000000000000000000';
    const ISSUE_REGEX = '|([A-Z]+\-[0-9]+)|';

    const TEMP = '/local/temp/';
    const RELEASE_EMAIL = 'release@domain.com';
    const GITPHP_URL = 'https://gitphp.service.url/';

    protected $old_revision,
              $new_revision,
              $reference,
              $ref_type,
              $ref_name,
              $master_merge = false,
              $staging_merge = false,
              $repo_name,
              $user;

    protected $default_branch = 'staging';

    public function __construct($old_revision, $new_revision, $reference)
    {
        $this->old_revision = $old_revision;
        $this->new_revision = $new_revision;
        $this->reference = $reference;

        preg_match('(refs/(heads|tags)/(.*))', $this->reference, $m);
        if (empty($m[1]) || empty($m[2])) {
            $this->error('Can not get branch/tag name or type from reference ' . $this->reference);
        }
        $this->ref_type = $m[1];
        $this->ref_name = $m[2];

        if ($this->ref_name == 'master') {
            $this->master_merge = true;
        }
        if ($this->ref_name == 'staging') {
            $this->staging_merge = true;
        }

        $this->repo_name = basename(getcwd());
        $process_user = posix_getpwuid(posix_geteuid());
        $this->user = $process_user['name'];
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getRefName()
    {
        return $this->ref_name;
    }

    public function getNewRevision()
    {
        return $this->new_revision;
    }

    public function getOldRevision()
    {
        return $this->old_revision;
    }

    public function getReference()
    {
        return $this->reference;
    }

    public function getRepoName()
    {
        return $this->repo_name;
    }

    public function masterMerge()
    {
        return $this->master_merge;
    }

    public function stagingMerge()
    {
        return $this->staging_merge;
    }

    public function firstPush()
    {
        return $this->old_revision == self::ZERO;
    }

    public function getDiff()
    {
        $diff = '';
        if ($this->firstPush()) {
            $diff = $this->_getDiffForNewBranch($this->new_revision);
        } elseif ($this->default_branch == $this->ref_name) { //master push
            $diff = $this->_getDiff($this->old_revision, $this->new_revision);
        } else {
            $diff = $this->_getDiffForOldBranch($this->old_revision, $this->new_revision);
        }
        return $diff;
    }

    protected function _getDiff($first_commit, $new_revision)
    {
        $this->exec(['log', "{$first_commit}..{$new_revision}"], $log);
        $log = implode("\n", $log);

        $this->exec(['diff', '-w', '-M', '-C', "{$first_commit}..{$new_revision}"], $diff);
        $diff = implode("\n", $diff);

        $this->exec(['diff', '--name-status', "{$first_commit}..{$new_revision}"], $output);
        $files = implode("\n", $output);

        return array('log' => $log, 'diff' => $diff, 'files' => $files, 'diff_no_merge' => $diff);
    }

    protected function _getDiffForNewBranch($new_revision)
    {
        $refspec = "{$this->default_branch}..{$new_revision}";
        
        $log = $this->log(['--no-merges', $refspec]);

        $list_commits = $this->generateCmd(['rev-list', '--no-merges', $refspec]);
        $show_patch   = $this->generateCmd(['show', '--pretty=format:', '{}']);
        // Generate full diff for each file in each commit, not just summary diff between two commits.
        $cmd = "{$list_commits} | xargs -I{} {$show_patch} | cat";
        $this->raw_exec($cmd, $diff);

        $this->exec(['show', '--pretty=format:', '--name-status', '--no-merges', $refspec], $files);

        $diff  = implode("\n", $diff);
        $files = implode("\n", array_unique($files));

        return array('log' => $log, 'diff' => $diff, 'files' => $files, 'diff_no_merge' => $diff);
    }

    protected function _getDiffForOldBranch($old_revision, $new_revision)
    {
        $diff_no_merge = '';
        $log = $this->log([$new_revision, "^{$old_revision}", "^{$this->default_branch}", '--']);

        $list_commits = $this->generateCmd(['rev-list', $new_revision, "^{$old_revision}", "^{$this->default_branch}", '--']);
        $show_patch   = $this->generateCmd(['show', '--pretty=format:']);
        $show_files   = $this->generateCmd(['show', '--pretty=format:', '--name-status']);

        $this->raw_exec("{$list_commits}             | xargs --no-run-if-empty {$show_patch}", $diff);
        $this->raw_exec("{$list_commits} --no-merges | xargs --no-run-if-empty {$show_patch}", $diff_no_merge);
        $this->raw_exec("{$list_commits}             | xargs --no-run-if-empty {$show_files}", $files);

        $diff          = implode("\n", $diff);
        $diff_no_merge = implode("\n", $diff_no_merge);
        $files         = implode("\n", array_unique($files));

        return array('log' => $log, 'diff' => $diff, 'diff_no_merge' => $diff_no_merge, 'files' => $files);
    }

    public function getDiffFiles(array $types = [], array $statuses = [])
    {
        $diff_files = [];
        if (empty($types)) {
            $diff_files = explode("\n", $this->getDiff()['files']);
        } elseif (preg_match_all('/^.*\.(' . implode('|', $types) . ')$/m', $this->getDiff()['files'], $m)) {
            $diff_files = array_reverse($m[0]);
        }

        $files = [];
        foreach (array_filter($diff_files) as $status_file) {
            list($status, $file) = explode("\t", $status_file);
            if (empty($statuses)) {
                if ('D' == $status && in_array($file, $files)) {
                    unset($files[array_search($file, $files)]);
                }
                if ('D' != $status && !in_array($file, $files)) {
                    $files[] = $file;
                }
            } else {
                if (in_array($status, $statuses)) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    public function getLog($from_revision, $to_revision, $arguments = [], $without_master = false, $without_staging = false)
    {
        if ($from_revision == self::ZERO) {
            $from_revision = $this->default_branch;
        }
        $commits_range = $from_revision . ".." . $to_revision;

        $without_master = $without_master ? '^master' : null;
        $without_staging = $without_staging ? '^staging' : null;

        if (!isset($arguments['format']) && !isset($arguments['pretty']) && !in_array('--oneline', $arguments)) {
            $arguments[] = '--oneline';
        }

        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            unset($arguments[$key]);
            $arguments[] = '--' . $key . '=' . $value;
        }

        $git_args = array_merge(['log'], $arguments, [$commits_range, $without_master, $without_staging], ['--']);

        $this->exec($git_args, $output, $exit_code);
        if ($exit_code) {
            // todo add error logging
            return [];
        }
        return $output;
    }

    public function getIssuesFromLog($old_revision, $new_revision, $without_master = false)
    {
        $issues_list = [];
        foreach ($this->getLog($old_revision, $new_revision, [], $without_master) as $log_entry) {
            if (!preg_match('/^[a-z0-9]+ ((\[.*?\]:? ?)+)/', $log_entry, $matches)) {
                continue;
            }
            $issues_string = $matches[1];
            if (!preg_match_all(self::ISSUE_REGEX, $issues_string, $matches)) {
                continue;
            }
            $issues_list = array_merge($issues_list, $matches[0]);
        }
        return array_unique($issues_list);
    }

    public static function sendEmail(
        $subject,
        $message,
        $to = self::RELEASE_EMAIL,
        $from = self::RELEASE_EMAIL,
        $contentType = 'text/plain',
        $message_id = false,
        $reply_message_id = false
    )
    {
        $headers = "From:" . $from . "\nContent-Type: " . $contentType . "; charset=utf-8";
        if ($message_id) {
            $headers .= "\nMessage-ID: $message_id";
        }
        if ($reply_message_id) {
            $headers .= "\nIn-Reply-To: $reply_message_id";
        }
        return mail($to, $subject, $message, $headers, '-f ' . $from);
    }

    public function log($arguments = [])
    {
        $git_args = array_merge(['log'], $arguments);
        $this->exec($git_args, $log_lines, $retval, true, true);
        return implode("\n", $log_lines);
    }

    public function generateCmd(array $args, $escape = true)
    {
        $cmd = 'git';
        if ($escape) {
            foreach ($args as $arg) {
                $cmd .= isset($arg) ? ' ' . escapeshellarg($arg) : '';
            }
        } else {
            foreach ($args as $arg) {
                $cmd .= isset($arg) ? (' ' . $arg) : '';
            }
        }

        return $cmd;
    }

    public function exec($git_args, &$out = null, &$retval = null, $print_error = true)
    {
        $cmd = $this->generateCmd($git_args);
        return $this->raw_exec($cmd, $out, $retval, $print_error);
    }

    public function raw_exec($cmd, &$out = null, &$retval = null, $print_error = true)
    {
        $retstr = exec($cmd, $out, $retval);
        if ($retval && $print_error) $this->error("Command '{$cmd}' execution failed. Command exited with non-zero code: [{$retval}]\n" . implode("\n", $out));
        return $retstr;
    }

    public function error($message)
    {
        echo($message . "\n");
        exit(1);
    }
}










