export default [
    {
        "name": "enrich-company",
        "label": "Enrich company by domain",
        "example": {
            "domain": "example.com",
            "waitForEnrichment": true
        }
    },
    {
        "name": "enrich-company-properties",
        "label": "Enrich company by properties",
        "example": {
            "body": {
                "name": "Example"
            }
        }
    },
    {
        "name": "enrich-companies-batch",
        "label": "Batch enrich companies",
        "example": {
            "body": {
                "domains": [
                    "example.com"
                ]
            }
        }
    },
    {
        "name": "get-company",
        "label": "Get company by ID",
        "example": {
            "id": "00000000-0000-0000-0000-000000000001"
        }
    },
    {
        "name": "search-companies",
        "label": "Search companies",
        "example": {
            "body": {
                "semanticQuery": "B2B software companies",
                "page": 1,
                "pageSize": 10
            }
        }
    },
    {
        "name": "similar-companies",
        "label": "Find similar companies",
        "example": {
            "body": {
                "domains": [
                    "example.com"
                ],
                "page": 1,
                "pageSize": 10
            },
            "waitForEnrichment": true
        }
    },
    {
        "name": "count-companies",
        "label": "Count matching companies",
        "example": {
            "body": {
                "countries": [
                    "US"
                ]
            }
        }
    },
    {
        "name": "count-similar-companies",
        "label": "Count similar companies",
        "example": {
            "body": {
                "domains": [
                    "example.com"
                ]
            },
            "waitForEnrichment": true
        }
    },
    {
        "name": "company-workforce",
        "label": "Get company workforce",
        "example": {
            "domain": "example.com"
        }
    },
    {
        "name": "search-people",
        "label": "Search professional profiles",
        "example": {
            "body": {
                "domains": [
                    "example.com"
                ],
                "positionQuery": [
                    "Engineer"
                ],
                "page": 1,
                "pageSize": 10
            }
        }
    },
    {
        "name": "lookup-person",
        "label": "Look up person by known email",
        "example": {
            "body": {
                "email": "jane@example.com"
            }
        }
    },
    {
        "name": "get-person",
        "label": "Get person by ID",
        "example": {
            "id": "9223372036854775806"
        }
    },
    {
        "name": "autocomplete-companies",
        "label": "Autocomplete company domains",
        "example": {
            "query": "example"
        }
    },
    {
        "name": "person-education",
        "label": "Get person education",
        "example": {
            "id": "9223372036854775806"
        }
    },
    {
        "name": "preview-companies",
        "label": "Preview companies (Scale plan)",
        "example": {
            "body": {
                "countries": [
                    "US"
                ]
            }
        }
    },
    {
        "name": "preview-similar-companies",
        "label": "Preview similar companies (Scale plan)",
        "example": {
            "body": {
                "domains": [
                    "example.com"
                ]
            },
            "waitForEnrichment": true
        }
    }
];
