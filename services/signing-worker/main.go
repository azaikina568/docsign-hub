// signing-worker — Go-сервис проверки подписи DocSign Hub.
// GO-1: только HTTP-каркас (health/metrics) + graceful shutdown. Потребитель событий и сама
// верификация (consume document.signed.v1 → пересчёт хешей → publish signature.verified.v1) — шаг GO-2.
package main

import (
	"context"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/gofiber/fiber/v2"
	"github.com/gofiber/fiber/v2/middleware/adaptor"
	"github.com/gofiber/fiber/v2/middleware/recover"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

func main() {
	app := newApp()
	addr := envOr("SIGNING_WORKER_ADDR", ":8090")

	// Грейсфул-стоп: по SIGTERM/SIGINT даём активным запросам доработать, потом гасим сервер.
	go func() {
		sigCh := make(chan os.Signal, 1)
		signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)
		<-sigCh

		ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()
		if err := app.ShutdownWithContext(ctx); err != nil {
			log.Printf("graceful shutdown error: %v", err)
		}
	}()

	log.Printf("signing-worker listening on %s", addr)
	if err := app.Listen(addr); err != nil {
		log.Fatalf("server stopped: %v", err)
	}
}

// newApp собирает Fiber-приложение. Вынесено отдельно, чтобы тесты гоняли те же роуты через app.Test.
func newApp() *fiber.App {
	app := fiber.New(fiber.Config{
		AppName:               "signing-worker",
		DisableStartupMessage: true,
	})
	app.Use(recover.New())

	app.Get("/healthz", func(c *fiber.Ctx) error {
		return c.JSON(fiber.Map{"status": "ok"})
	})

	// Метрики Prometheus: монтируем стандартный promhttp-хендлер через fiber-адаптер.
	app.Get("/metrics", adaptor.HTTPHandler(promhttp.Handler()))

	return app
}

func envOr(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}

	return fallback
}
