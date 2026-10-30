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

ENTRYPOINT ["java","-jar","/usr/app/niblirc-jar-with-dependencies.jar","/config/config.txt"]
