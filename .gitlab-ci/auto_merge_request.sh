#!/usr/bin/env bash
# (c) Copyright by authors of the Tiki Manager Project
# 
# All Rights Reserved. See copyright.txt for details and a complete list of authors.
# Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.

# Script adapted from https://about.gitlab.com/2017/09/05/how-to-automatically-create-a-new-mr-on-gitlab-with-gitlab-ci/

# This script is designed to be use from gitlab ci
# sh auto_merge_request.sh <MR_TITLE>
# Example:
#   sh auto_merge_request.sh # If empty if creates a merge request with WIP: <branch_name>
#   sh auto_merge_request.sh "[FIX] Fix something"
#   sh auto_merge_request.sh "WIP: [FIX] Fix something" # A Work-In-Progress MR

# Extract the host where the server is running, and add the URL to the APIs
[[ $HOST =~ ^https?://[^/]+ ]] && HOST="${BASH_REMATCH[0]}/api/v4/projects/"

# Look which is the default branch
if [ -z $TARGET_BRANCH ]; then
  TARGET_BRANCH=`curl --silent "${HOST}${CI_PROJECT_ID}" --header "PRIVATE-TOKEN:${PRIVATE_TOKEN}" | python3 -c "import sys, json; print(json.load(sys.stdin)['default_branch'])"`;
fi;

if [ -z "$1" ]; then
    MR_TITLE="WIP: ${SOURCE_BRANCH}"
else
    MR_TITLE="$1"
fi;

if [ -z $TARGET_PROJECT_ID ]; then 
  TARGET_PROJECT_ID=${CI_PROJECT_ID}
fi;

if [ -z $SET_MERGE ]; then
  SET_MERGE=0
fi;

# The description of our new MR, we want to remove the branch after the MR has
# been closed
BODY="{
    \"id\": ${CI_PROJECT_ID},
    \"source_branch\": \"${SOURCE_BRANCH}\",
    \"target_branch\": \"${TARGET_BRANCH}\",
    \"remove_source_branch\": true,
    \"merge_when_pipeline_succeeds\": true,
    \"title\": \"${MR_TITLE}\",
    \"assignee_id\":\"${GITLAB_USER_ID}\",
    \"target_project_id\":\"${TARGET_PROJECT_ID}\"
}";

# Require a list of all the merge request and take a look if there is already
# one with the same source branch
LISTMR=`curl --silent "${HOST}${CI_PROJECT_ID}/merge_requests?state=opened" --header "PRIVATE-TOKEN:${PRIVATE_TOKEN}"`;
COUNTBRANCHES=`echo ${LISTMR} | grep -o "\"source_branch\":\"${SOURCE_BRANCH}\"" | wc -l`;

# No MR found, let's create a new one
if [ ${COUNTBRANCHES} -eq "0" ]; then
    CREATEMR=`curl -sL -w "%{http_code}" -i -X POST "${HOST}${CI_PROJECT_ID}/merge_requests" \
        --header "PRIVATE-TOKEN:${PRIVATE_TOKEN}" \
        --header "Content-Type: application/json" \
        --data "${BODY}"`;

    [[ $CREATEMR =~ \"iid\":([0-9]+) ]] && MR_ID=${BASH_REMATCH[1]}
    [[ $CREATEMR =~ [0-9]+$ ]] && STATUS=${BASH_REMATCH[0]}

    if [ ${STATUS} -eq "201" ]; then
      echo "Opened a new merge request: ${MR_TITLE} and assigned to you";

      if [ ${SET_MERGE} -eq 1 ]; then
        # Mark MR as accepted (auto-merge if pipeline succeeds)
        curl -sL -X PUT "${HOST}${CI_PROJECT_ID}/merge_requests/${MR_ID}/merge" \
          --header "PRIVATE-TOKEN:${PRIVATE_TOKEN}" \
          --header "Content-Type: application/json" \
          --data "{\"merge_when_pipeline_succeeds\": true}";
      fi;

      exit;
    else
      echo "Failed to open a new merge request. Server responded with HTTP status: ${STATUS}."
      exit 1;
    fi;

fi

echo "No new merge request opened";
