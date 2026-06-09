#!/usr/bin/env python3
"""
SuiteCRM / SinergiaCRM API V8 Client (Python CLI)
Connects using client_credentials grant, gets an access token, and lists contacts.

Configuration is read from .env in the same directory.
Copy .env.example to .env and set your credentials.
"""

import json
import os
import sys
import urllib.request
import urllib.error

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))


def load_env():
    env_file = os.path.join(SCRIPT_DIR, ".env")
    if not os.path.exists(env_file):
        sys.exit("Error: .env file not found. Copy .env.example to .env and configure it.")

    env = {}
    with open(env_file, "r") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            key, _, value = line.partition("=")
            env[key.strip()] = value.strip()
    return env


ENV = load_env()
BASE_URL = ENV.get("SUITECRM_BASE_URL", "http://localhost:8000").rstrip("/")
CLIENT_ID = ENV.get("OAUTH2_CLIENT_ID", "")
CLIENT_SECRET = ENV.get("OAUTH2_CLIENT_SECRET", "")
HEADERS_JSONAPI = {
    "Content-Type": "application/vnd.api+json",
    "Accept": "application/vnd.api+json",
}


def get_access_token():
    url = f"{BASE_URL}/Api/access_token"
    body = json.dumps({
        "grant_type": "client_credentials",
        "client_id": CLIENT_ID,
        "client_secret": CLIENT_SECRET,
    }).encode("utf-8")

    req = urllib.request.Request(url, data=body, headers=HEADERS_JSONAPI, method="POST")
    try:
        with urllib.request.urlopen(req) as resp:
            data = json.loads(resp.read().decode("utf-8"))
            return data["access_token"]
    except urllib.error.HTTPError as e:
        error_body = e.read().decode("utf-8")
        raise RuntimeError(f"Auth failed ({e.code}): {error_body}")


def api_get(endpoint, token, params=None):
    if params:
        parts = [f"{urllib.request.quote(str(k))}={urllib.request.quote(str(v))}" for k, v in params.items()]
        url = f"{BASE_URL}{endpoint}?{'&'.join(parts)}"
    else:
        url = f"{BASE_URL}{endpoint}"

    headers = {**HEADERS_JSONAPI, "Authorization": f"Bearer {token}"}
    req = urllib.request.Request(url, headers=headers, method="GET")

    try:
        with urllib.request.urlopen(req) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        error_body = e.read().decode("utf-8")
        raise RuntimeError(f"API error ({e.code}): {error_body}")


def fetch_contacts(token, page_size=20, max_pages=None):
    all_contacts = []
    page_number = 1

    while True:
        params = {"page[number]": page_number, "page[size]": page_size}
        data = api_get("/Api/V8/module/Contacts", token, params)
        contacts = data.get("data", [])
        if not contacts:
            break

        all_contacts.extend(contacts)
        total_pages = data.get("meta", {}).get("total-pages", 1)
        print(f"  Page {page_number}/{total_pages} ({len(contacts)} contacts)")

        if page_number >= total_pages or (max_pages and page_number >= max_pages):
            break
        page_number += 1

    return all_contacts, total_pages


def main():
    all_flag = "--all" in sys.argv
    max_pages = None if all_flag else 3
    page_size = 20

    print("=== SuiteCRM API V8 Client ===")
    print(f"Base URL: {BASE_URL}")
    print()

    print("Step 1: Getting access token...")
    token = get_access_token()
    print(f"  Token: {token[:50]}...")
    print()

    label = "all pages" if all_flag else f"up to {max_pages} pages"
    print(f"Step 2: Fetching contacts ({label}, {page_size} per page)...")
    contacts, total_pages = fetch_contacts(token, page_size=page_size, max_pages=max_pages)
    print()

    estimated_total = total_pages * page_size
    print(f"Contacts fetched: {len(contacts)} of ~{estimated_total} total")
    print()

    for i, contact in enumerate(contacts, 1):
        attrs = contact.get("attributes", {})
        contact_id = contact.get("id", "N/A")
        first_name = attrs.get("first_name", "")
        last_name = attrs.get("last_name", "")
        email = attrs.get("email1", "")
        full_name = attrs.get("name", f"{first_name} {last_name}".strip())
        print(f"  {i}. ID: {contact_id}")
        print(f"     Name: {full_name}")
        if email:
            print(f"     Email: {email}")
        print()

    if not all_flag and estimated_total > len(contacts):
        print(f"Showing {len(contacts)} of ~{estimated_total} contacts.")
        print("Use --all to fetch all pages.")


if __name__ == "__main__":
    main()
