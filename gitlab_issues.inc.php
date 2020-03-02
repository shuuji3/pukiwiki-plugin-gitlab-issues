<?php

function plugin_gitlab_issues_convert() {
    $num  = func_num_args();
    if ( $num < 3 ) {
        return "Usage: #gitlab_issues([domain],[api_token],[project_id],[limit = 10])";
    }

    $args = func_get_args();
    $domain = $args[0];
    $api_token = $args[1];
    $project_id = $args[2];
    $limit = $args[3];
    if ($limit == '') {
      $limit = 5;
    }

    $js_code = <<<EOC
import domify from 'https://cdn.pika.dev/domify';
import dayjs from 'https://cdn.pika.dev/dayjs';

document.addEventListener('DOMContentLoaded', async () => {
  const issues = (await getIssues()).slice(0, $limit);
  const issuesTableDOM = makeIssuesTableDOM(issues);
  document.querySelector('#gitlab-issues-placeholder').appendChild(domify(issuesTableDOM));
  document.querySelector('#gitlab-issues-loading').style = 'display: none;';
});

async function getIssues() {
  const url = 'https://$domain/api/v4/projects/$project_id/issues';
  const options = { headers: { Authorization: `Bearer $api_token` }};
  const res = await fetch(url, options);
  const	issues = await res.json();
  issues.sort((issue1, issue2) => dayjs(issue2.updated_at) - dayjs(issue1.updated_at));
  return issues;
}

function makeIssuesTableDOM(issues) {
  const issueRowDOMs = issues.map(buildIssueRowHTML).join('\\n');
  const issuesTableDOM = `
<style>
  .gitlab-issues table {
    border-collapse: collapse;
    border-spacing: 0;
    margin: 0.8em;
  }
  .gitlab-issues td, th {
    border: 1px solid gray;
    padding: 0.4em;
  }
  .gitlab-issues th {
    background-color: #eef5ff;
  }
  .gitlab-issues td, .gitlab-issues th {
    font-size: 1em;
  }

  .gitlab-issues a.icon:before {
    display: none;
  }
</style>
<div class="gitlab-issues">
  <table>
    <thead>
      <th>作成日</th>
      <th>更新日</th>
      <th>タイトル</th>
      <th>タグ</th>
      <th>作成者</th>
      <th>担当者</th>
    </thead>
    <tbody>
    \${issueRowDOMs}
    </tbody>
  </table>
</div>
`;
  return issuesTableDOM;
}

function buildIssueRowHTML(issue) {
  const authorHTML = `
<a class="icon" href="\${issue.author.web_url}">
  <img src="\${issue.author.avatar_url}" title="\${issue.author.name} (@\${issue.author.username})" width="16" alt="\${issue.author.name} (@\${issue.author.username})">
  @\${issue.author.username}
</a>`;

  const assigneeHTML = issue.assignee == null ? '-' : `
<a class="icon" href="\${issue.assignee.web_url}">
  <img src="\${issue.assignee.avatar_url}" title="\${issue.assignee.name} (@\${issue.assignee.username})" width="16" alt="\${issue.author.name} (@\${issue.author.username})">
  @\${issue.assignee.username}
</a>`;

  return `
<tr>
  <td>\${formatDateTime(issue.created_at)}</td>
  <td>\${formatDateTime(issue.updated_at)}</td>
  <td><a href="\${issue.web_url}">\${issue.title}</a></td>
  <td>\${issue.labels.join(', ')}</td>
  <td>\${authorHTML}</td>
  <td>\${assigneeHTML}</td>
</tr>`;
}

function formatDateTime(datetime) {
  return dayjs(datetime).format('YYYY-MM-DD(ddd)');
}
EOC;

    return '<div id="gitlab-issues-placeholder"></div>' .
    '<div id="gitlab-issues-loading">&#9203; Loading GitLab issues...</div>' .
    '<script type="module">' . $js_code . '</script>';
}
?>
