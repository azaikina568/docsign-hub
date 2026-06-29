package main

import (
	"io"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestHealthz(t *testing.T) {
	resp, err := newApp().Test(httptest.NewRequest("GET", "/healthz", nil))
	if err != nil {
		t.Fatalf("request failed: %v", err)
	}
	if resp.StatusCode != 200 {
		t.Fatalf("want 200, got %d", resp.StatusCode)
	}

	body, _ := io.ReadAll(resp.Body)
	if !strings.Contains(string(body), `"status":"ok"`) {
		t.Fatalf("unexpected body: %s", body)
	}
}

func TestMetrics(t *testing.T) {
	resp, err := newApp().Test(httptest.NewRequest("GET", "/metrics", nil))
	if err != nil {
		t.Fatalf("request failed: %v", err)
	}
	if resp.StatusCode != 200 {
		t.Fatalf("want 200, got %d", resp.StatusCode)
	}
}

func TestEnvOr(t *testing.T) {
	t.Setenv("SOME_KEY", "")
	if got := envOr("SOME_KEY", "fallback"); got != "fallback" {
		t.Fatalf("empty env should yield fallback, got %q", got)
	}

	t.Setenv("SOME_KEY", "value")
	if got := envOr("SOME_KEY", "fallback"); got != "value" {
		t.Fatalf("set env should win, got %q", got)
	}
}
