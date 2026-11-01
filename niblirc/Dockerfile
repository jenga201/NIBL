# syntax=docker/dockerfile:1

###############################################################################
# build: compile the fat jar with Maven inside the image
###############################################################################
FROM maven:3.9-eclipse-temurin-21 AS build

WORKDIR /usr/src/app

# Dependency manifest first, so this layer is cached until pom.xml changes
COPY pom.xml ./
RUN mvn -B dependency:go-offline

COPY src ./src
RUN mvn -B clean package

###############################################################################
# final: distroless runtime image
###############################################################################
FROM gcr.io/distroless/java21-debian12

COPY --from=build /usr/src/app/target/niblirc-jar-with-dependencies.jar /usr/app/niblirc-jar-with-dependencies.jar

# Distroless has no shell or coreutils, so ship a static busybox for the
# healthcheck probe (the uclibc variant is statically linked; the default
# busybox image is glibc-linked and won't run in distroless).
COPY --from=busybox:1.36.1-uclibc /bin/busybox /bin/busybox

# Liveness: the JVM running the niblirc jar is alive (the bot is an outbound
# IRC client with no persistent listening port to probe, so process liveness
# is the signal we can rely on inside this minimal image).
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["/bin/busybox", "pgrep", "-f", "niblirc-jar-with-dependencies.jar"]

ENTRYPOINT ["java","-jar","/usr/app/niblirc-jar-with-dependencies.jar","/config/config.txt"]
